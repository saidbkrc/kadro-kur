<?php

namespace Tests\Feature;

use App\Livewire\Groups;
use App\Livewire\Matches;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use App\Notifications\MatchPushNotification;
use App\Services\MatchScheduler;
use App\Services\PlayerBadges;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KadroFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGroup(User $owner): Group
    {
        $group = Group::create(['owner_id' => $owner->id, 'name' => 'Salı Maçları']);
        $group->members()->attach($owner->id, ['role' => 'owner']);
        $group->ensurePlayerFor($owner);

        return $group;
    }

    protected function addMember(Group $group, ?User $user = null): Player
    {
        $user ??= User::factory()->create();
        $group->members()->attach($user->id, ['role' => 'member']);

        return $group->ensurePlayerFor($user);
    }

    protected function makeMatch(Group $group, int $capacity = 14): FootballMatch
    {
        return $group->matches()->create([
            'created_by' => $group->owner_id,
            'title' => 'Salı 21:00 maçı',
            'starts_at' => now()->addDays(2),
            'capacity' => $capacity,
        ]);
    }

    public function test_grup_kurulur_davetle_katilinir_ve_oyuncu_kaydi_acilir(): void
    {
        $owner = User::factory()->create();

        Livewire::actingAs($owner)
            ->test(Groups\Index::class)
            ->set('name', 'Salı Maçları')
            ->call('create')
            ->assertHasNoErrors();

        $group = Group::firstWhere('name', 'Salı Maçları');
        $this->assertNotNull($group->playerFor($owner), 'Kurucuya oyuncu kaydı açılmalı');

        $friend = User::factory()->create();
        Livewire::actingAs($friend)
            ->test(Groups\Join::class, ['code' => $group->invite_code])
            ->call('join');

        $this->assertTrue($group->isMember($friend));
        $this->assertNotNull($group->playerFor($friend), 'Katılan üyeye oyuncu kaydı açılmalı');
    }

    public function test_misafir_eklenir_ve_hesapla_eslesir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);

        Livewire::actingAs($owner)
            ->test(Groups\Show::class, ['group' => $group])
            ->set('guestName', 'Mahmut')
            ->call('addGuest')
            ->assertHasNoErrors();

        $guest = $group->players()->whereNull('user_id')->firstWhere('name', 'Mahmut');
        $this->assertNotNull($guest);

        // Mahmut kayıt olup gruba katılır → boş kaydı açılır
        $mahmut = User::factory()->create(['name' => 'Mahmut B.']);
        $this->addMember($group, $mahmut);

        // Başkan misafir kaydıyla eşleştirir → boş kayıt silinir, puanlı kayıt bağlanır
        Livewire::actingAs($owner)
            ->test(Groups\Show::class, ['group' => $group])
            ->call('linkGuest', $guest->id, $mahmut->id);

        $this->assertSame(1, $group->players()->where('user_id', $mahmut->id)->count());
        $this->assertSame($guest->id, $group->playerFor($mahmut)->id, 'Misafir kaydı (geçmişiyle) kullanıcıya bağlanmalı');
    }

    public function test_ozellik_puanlamasi_anonim_ortalama_ve_ovr(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $player = $this->addMember($group);

        $player->update(['positions' => ['FV']]);

        // İki üye puanlar: şut 10 ve şut 6 → ortalama 8
        Livewire::actingAs($owner)
            ->test(Groups\Rate::class, ['group' => $group])
            ->call('select', $player->id)
            ->set('scores.sut', 10)
            ->call('save');

        $rater2 = $this->addMember($group)->user;
        Livewire::actingAs($rater2)
            ->test(Groups\Rate::class, ['group' => $group])
            ->call('select', $player->id)
            ->set('scores.sut', 6)
            ->call('save');

        $player->refresh()->load('attributeRatings');
        $this->assertEqualsWithDelta(8.0, $player->averageAttributes()['sut'], 0.01);
        $this->assertGreaterThan(5.0, $player->overall(), 'Şut ortalaması 8 olunca forvet OVR 5 üstüne çıkmalı');

        // Kendine puan veremez
        $own = $group->playerFor($owner);
        Livewire::actingAs($owner)
            ->test(Groups\Rate::class, ['group' => $group])
            ->call('select', $own->id)
            ->assertStatus(403);
    }

    public function test_rsvp_yedek_listesi_player_bazli_calisir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group, capacity: 4);

        $players = collect(range(1, 6))->map(fn () => $this->addMember($group));

        foreach ($players as $player) {
            $match->setRsvp($player, 'going');
        }

        $this->assertSame(4, $match->confirmedCount());
        $this->assertSame(1, $match->rsvps()->where('player_id', $players[4]->id)->value('waitlist_position'));

        // Asıl listeden biri çekilince yedekteki ilk kişi terfi eder
        $match->setRsvp($players[0], 'not_going');
        $this->assertNull($match->rsvps()->where('player_id', $players[4]->id)->value('waitlist_position'));
        $this->assertSame(1, $match->rsvps()->where('player_id', $players[5]->id)->value('waitlist_position'));
    }

    public function test_baskan_uye_ve_misafir_adina_rsvp_isaretler(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group);

        $member = $this->addMember($group);
        $guest = $group->players()->create(['name' => 'Misafir', 'positions' => ['OS']]);

        $component = Livewire::actingAs($owner)->test(Matches\Show::class, ['match' => $match]);

        // Başkan hem kayıtlı üye hem misafir adına işaretler
        $component->call('setPlayerRsvp', $member->id, 'going');
        $component->call('setPlayerRsvp', $guest->id, 'going');

        $this->assertSame('going', $match->rsvps()->where('player_id', $member->id)->value('status'));
        $this->assertSame('going', $match->rsvps()->where('player_id', $guest->id)->value('status'));

        // Başkan olmayan biri başkasının adına işaretleyemez
        Livewire::actingAs($member->user)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('setPlayerRsvp', $guest->id, 'not_going')
            ->assertStatus(403);
    }

    public function test_kadro_kurulur_kurallara_uyar_ve_oylamayla_onaylanir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group, capacity: 6);

        $ownPlayer = $group->playerFor($owner);
        $match->setRsvp($ownPlayer, 'going');

        $players = collect(range(1, 5))->map(fn () => $this->addMember($group));
        foreach ($players as $player) {
            $match->setRsvp($player, 'going');
        }

        // Kural: owner ile ilk üye ayrı takımlarda
        $group->rules()->create([
            'player_a_id' => $ownPlayer->id,
            'player_b_id' => $players[0]->id,
            'type' => 'apart',
        ]);

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('buildSquads')
            ->assertHasNoErrors();

        $match->refresh();
        $this->assertSame('voting', $match->squad_status);

        $teams = $match->rsvps()->pluck('team', 'player_id');
        $this->assertNotSame($teams[$ownPlayer->id], $teams[$players[0]->id], 'Apart kuralı uygulanmalı');
        $this->assertSame(3, $teams->filter(fn ($t) => $t === 'A')->count());

        // %60 onay: 6 oyuncunun hepsi hesaplı → 4 evet gerekli (ceil(6*0.6))
        $summary = $match->squadVoteSummary();
        $this->assertSame(4, $summary['needed']);

        $voters = [$owner, $players[0]->user, $players[1]->user];
        foreach ($voters as $voter) {
            $match->castSquadVote($voter, true);
        }
        $this->assertSame('voting', $match->refresh()->squad_status, '3 evet yetmez');

        $match->castSquadVote($players[2]->user, true);
        $this->assertSame('approved', $match->refresh()->squad_status, '4. evet ile kadro kesinleşir');

        // Asıl liste değişirse kadro sıfırlanır
        $match->setRsvp($players[3], 'not_going');
        $this->assertSame('none', $match->refresh()->squad_status);
    }

    public function test_kadro_sablonu_kaydedilir_ve_yuklenir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group, capacity: 6);

        $ownPlayer = $group->playerFor($owner);
        $match->setRsvp($ownPlayer, 'going');
        $players = collect(range(1, 5))->map(fn () => $this->addMember($group));
        foreach ($players as $player) {
            $match->setRsvp($player, 'going');
        }

        $component = Livewire::actingAs($owner)->test(Matches\Show::class, ['match' => $match]);

        // Kadro kur + şablon olarak kaydet
        $component->call('buildSquads')
            ->set('templateName', 'Çekirdek Kadro')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('squad_templates', ['group_id' => $group->id, 'name' => 'Çekirdek Kadro']);
        $template = $group->squadTemplates()->first();
        $this->assertCount(6, $template->teams);

        // Yeni maçta şablonu yükle → oyuncular "going" + takımlı + oylamaya sunulu
        $match2 = $this->makeMatch($group, capacity: 6);
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match2])
            ->call('applyTemplate', $template->id)
            ->assertHasNoErrors();

        $match2->refresh();
        $this->assertSame('voting', $match2->squad_status);
        $this->assertSame(6, $match2->confirmedCount());
        $this->assertSame(6, $match2->rsvps()->whereNotNull('team')->count());
    }

    public function test_sablon_grup_basina_uc_ile_sinirli(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);

        foreach (['A', 'B', 'C'] as $name) {
            $group->squadTemplates()->create(['name' => $name, 'teams' => [1 => 'A']]);
        }

        $match = $this->makeMatch($group);
        $match->setRsvp($group->playerFor($owner), 'going');
        collect(range(1, 3))->each(fn () => $match->setRsvp($this->addMember($group), 'going'));

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('buildSquads')
            ->set('templateName', 'Dördüncü')
            ->call('saveTemplate')
            ->assertHasErrors('template');

        $this->assertSame(3, $group->squadTemplates()->count());
    }

    public function test_sonuc_girilir_mvp_oylamasi_acilir_oy_degistirilemez(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group);

        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 7)
            ->set('teamBScore', 5)
            ->set('goals', [$friend->id => 3])
            ->call('saveResult')
            ->assertHasNoErrors();

        $match->refresh();
        $this->assertSame('completed', $match->status);
        $this->assertNotNull($match->mvp_closes_at);
        $this->assertTrue($match->mvpOpen());
        $this->assertSame(3, $match->goals()->where('player_id', $friend->id)->value('count'));

        // Oy verilir, ikinci oy ilkini değiştirmez
        Livewire::actingAs($friend->user)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('voteMvp', $ownPlayer->id);

        Livewire::actingAs($friend->user)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('voteMvp', $friend->id); // kendine oy — zaten oy verdiği için de yazılmaz

        $this->assertSame(1, $match->mvpVotes()->count());
        $this->assertSame($ownPlayer->id, $match->mvpVotes()->first()->player_id);

        // 1 hafta geçince oylama kapanır (varsayılan pencere 168 saat)
        $this->travel(8)->days();
        $this->assertFalse($match->refresh()->mvpOpen());

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('voteMvp', $friend->id)
            ->assertStatus(403);
    }

    public function test_performans_penceresi_mvpden_bagimsiz_bir_hafta_acik(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        // Dün oynanmış, MVP penceresi kapanmış maç
        $match = $group->matches()->create([
            'created_by' => $owner->id,
            'title' => 'Dünkü maç',
            'starts_at' => now()->subDay(),
            'capacity' => 14,
            'status' => 'completed',
            'team_a_score' => 3,
            'team_b_score' => 2,
            'mvp_closes_at' => now()->subHours(2),
        ]);
        $match->rsvps()->create(['player_id' => $ownPlayer->id, 'status' => 'going', 'team' => 'A']);
        $match->rsvps()->create(['player_id' => $friend->id, 'status' => 'going', 'team' => 'B']);

        // MVP kapandı ama performans hâlâ açık (pencere: maç saati + 168 saat)
        $this->assertFalse($match->mvpOpen());
        $this->assertTrue($match->perfOpen());

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('ratePerformance', $friend->id, 8)
            ->assertHasNoErrors();

        $this->assertSame(1, $match->performanceRatings()->count());

        // 1 hafta dolunca performans da kapanır
        $this->travel(7)->days();
        $this->assertFalse($match->refresh()->perfOpen());

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('ratePerformance', $friend->id, 9)
            ->assertStatus(403);
    }

    public function test_baskan_gecmis_mac_skorunu_duzeltir_bildirim_tekrarlanmaz(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 3)->set('teamBScore', 1)
            ->set('goals', [$friend->id => 2])
            ->call('saveResult');

        $closesAt = $match->refresh()->mvp_closes_at;
        $this->assertNull($match->result_edited_at, 'İlk kayıt düzenleme sayılmaz');

        // Skor düzeltilir: bildirim gitmez (rozetler ilk kayıtta verildi), oylama süresi uzamaz
        Notification::fake();
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match->refresh()])
            ->set('teamAScore', 4)->set('teamBScore', 2)
            ->call('saveResult')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
        $match->refresh();
        $this->assertSame(4, $match->team_a_score);
        $this->assertSame(2, $match->team_b_score);
        $this->assertSame(2, $match->goals()->where('player_id', $friend->id)->value('count'));
        $this->assertTrue($closesAt->equalTo($match->mvp_closes_at), 'Oylama penceresi uzamamalı');

        // Şeffaflık kaydı düşer
        $this->assertNotNull($match->result_edited_at);
        $this->assertSame($owner->id, $match->result_edited_by);

        // Yönetici olmayan üye skoru düzenleyemez
        Livewire::actingAs($friend->user)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 9)->set('teamBScore', 0)
            ->call('saveResult')
            ->assertStatus(403);
    }

    public function test_baskan_hatirlatma_gonderir_ve_30dk_sinirlanir(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $cevapsiz = $this->addMember($group);   // RSVP vermedi → hatırlatma almalı
        $gelen = $this->addMember($group);      // "geliyorum" dedi → almamalı

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($gelen, 'going');

        \Illuminate\Support\Facades\RateLimiter::clear('manuel-bildirim:'.$group->id);

        $component = Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('sendReminder', 'rsvp');

        Notification::assertSentTo($cevapsiz->user, MatchPushNotification::class);
        Notification::assertNotSentTo($gelen->user, MatchPushNotification::class);
        Notification::assertNotSentTo($owner, MatchPushNotification::class);
        $component->assertSet('reminderNotice', '✅ Hatırlatma 1 kişiye gönderildi.');

        // 30 dk dolmadan ikinci gönderim engellenir
        Notification::fake();
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('sendReminder', 'rsvp')
            ->assertSet('reminderNotice', fn ($v) => str_contains((string) $v, 'Çok sık'));
        Notification::assertNothingSent();

        // Yönetici olmayan gönderemez
        Livewire::actingAs($gelen->user)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('sendReminder', 'rsvp')
            ->assertStatus(403);
    }

    public function test_rozet_kazanilinca_bildirim_gider_tekrar_gitmez(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');

        // Skor girilir → İlk Maç + (golcüye) İlk Gol rozetleri kazanılır
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 2)->set('teamBScore', 1)
            ->set('goals', [$friend->id => 1])
            ->call('saveResult');

        Notification::assertSentTo($friend->user, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'rozet') && str_contains($n->body, 'İlk Gol'));
        Notification::assertSentTo($owner, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'rozet') && str_contains($n->body, 'İlk Maç'));

        $this->assertTrue($friend->badges()->where('badge_key', 'first_goal')->exists());

        // Aynı grup tekrar senkronlanır → yeni rozet yok, bildirim gitmez
        Notification::fake();
        app(PushNotifier::class)->syncBadgesAndNotify($group);
        Notification::assertNothingSent();
    }

    public function test_mac_ozeti_ertesi_gun_bir_kez_gider(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        // Dün oynanmış, özeti gönderilmemiş maç
        $match = $group->matches()->create([
            'created_by' => $owner->id,
            'title' => 'Dünkü maç',
            'starts_at' => now()->subHours(25),
            'capacity' => 14,
            'status' => 'completed',
            'team_a_score' => 4,
            'team_b_score' => 2,
            'mvp_closes_at' => now()->addDays(5),
        ]);
        $match->rsvps()->create(['player_id' => $ownPlayer->id, 'status' => 'going', 'team' => 'A']);
        $match->rsvps()->create(['player_id' => $friend->id, 'status' => 'going', 'team' => 'B']);
        $match->goals()->create(['player_id' => $friend->id, 'count' => 2]);
        $match->mvpVotes()->create(['voter_id' => $owner->id, 'player_id' => $friend->id]);

        app(PushNotifier::class)->sendDueDigests();

        Notification::assertSentTo($friend->user, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'Maç özeti') && str_contains($n->body, '4 - 2') && str_contains($n->body, 'MVP'));
        $this->assertNotNull($match->refresh()->digest_sent_at);

        // İkinci çalıştırma aynı maç için özet göndermez
        Notification::fake();
        app(PushNotifier::class)->sendDueDigests();
        Notification::assertNotSentTo($friend->user, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'Maç özeti'));
    }

    public function test_haftalik_otomatik_mac_olusur(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);

        $group->update([
            'match_day' => 2, // Salı
            'match_time' => '21:00',
            'default_location' => 'Yıldız Halı Saha',
            'auto_schedule' => true,
        ]);

        $match = app(MatchScheduler::class)->ensureUpcomingMatch($group->refresh());

        $this->assertNotNull($match);
        $this->assertSame(2, $match->starts_at->dayOfWeekIso);
        $this->assertSame('21:00', $match->starts_at->format('H:i'));
        $this->assertTrue($match->starts_at->isFuture());
        $this->assertSame('Yıldız Halı Saha', $match->location);

        // Gelecek maç varken ikinciyi açmaz
        $this->assertNull(app(MatchScheduler::class)->ensureUpcomingMatch($group));
    }

    public function test_mac_iptali_haftayi_atlar_sonraki_hafta_acilir(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $member = $this->addMember($group);

        $group->update([
            'match_day' => 2, // Salı
            'match_time' => '21:00',
            'auto_schedule' => true,
        ]);

        $match = app(MatchScheduler::class)->ensureUpcomingMatch($group->refresh());
        $cancelledDate = $match->starts_at->copy();

        // Başkan maçı iptal eder (tek seferlik erteleme)
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('cancelMatch');

        $this->assertSame('cancelled', $match->refresh()->status);

        // Üyeye iptal bildirimi gider
        Notification::assertSentTo($member->user, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'iptal'));

        // Sıradaki haftanın maçı otomatik açıldı — iptal edilen slot değil, 1 hafta sonrası
        $next = $group->matches()->where('status', 'scheduled')->where('starts_at', '>=', now())->first();
        $this->assertNotNull($next);
        $this->assertTrue($next->starts_at->equalTo($cancelledDate->addWeek()), 'Yeni maç iptal edilenin 1 hafta sonrası olmalı');

        // Scheduler tekrar çalışsa bile iptal edilen slotu yeniden AÇMAZ
        $this->assertNull(app(MatchScheduler::class)->ensureUpcomingMatch($group));
        $this->assertSame(1, $group->matches()->where('starts_at', $cancelledDate)->count());
    }

    public function test_davet_linki_girissiz_acilir_kayit_sonrasi_geri_donulur(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);

        // Girişsiz: davet sayfası açılır, kayıt çağrısı görünür
        $this->get(route('groups.join', $group->invite_code))
            ->assertOk()
            ->assertSee('Salı Maçları')
            ->assertSee('Kayıt Ol');

        // Kayıt olunca davet sayfasına geri dönülür (url.intended)
        \Livewire\Volt\Volt::test('pages.auth.register')
            ->set('name', 'Yeni Oyuncu')
            ->set('email', 'yeni@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect(route('groups.join', $group->invite_code));

        // Artık giriş yapmış halde katılabilir
        $newUser = User::firstWhere('email', 'yeni@example.com');
        Livewire::actingAs($newUser)
            ->test(Groups\Join::class, ['code' => $group->invite_code])
            ->call('join');

        $this->assertTrue($group->refresh()->isMember($newUser));
        $this->assertNotNull($group->playerFor($newUser));
    }

    public function test_uye_gruptan_ayrilir_ve_baskan_uye_cikarir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);

        // Geçmişi olan üye (puan almış) → ayrılınca misafire döner
        $veteran = $this->addMember($group);
        \App\Models\AttributeRating::create([
            'player_id' => $veteran->id, 'rater_id' => $owner->id, 'scores' => ['hiz' => 7],
        ]);

        Livewire::actingAs($veteran->user)
            ->test(Groups\Show::class, ['group' => $group])
            ->call('leaveGroup');

        $this->assertFalse($group->isMember($veteran->user));
        $this->assertNull($veteran->refresh()->user_id, 'Geçmişi olan oyuncu misafire dönmeli');

        // Geçmişi olmayan üyeyi başkan çıkarır → oyuncu kaydı tamamen silinir
        $rookie = $this->addMember($group);
        Livewire::actingAs($owner)
            ->test(Groups\Show::class, ['group' => $group])
            ->call('removeMember', $rookie->user->id);

        $this->assertFalse($group->isMember($rookie->user));
        $this->assertDatabaseMissing('players', ['id' => $rookie->id]);

        // Başkan ayrılamaz, normal üye başkasını çıkaramaz
        Livewire::actingAs($owner)->test(Groups\Show::class, ['group' => $group])
            ->call('leaveGroup')->assertStatus(403);

        $a = $this->addMember($group);
        $b = $this->addMember($group);
        Livewire::actingAs($a->user)->test(Groups\Show::class, ['group' => $group])
            ->call('removeMember', $b->user->id)->assertStatus(403);
    }

    public function test_baskan_grubu_siler(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $this->makeMatch($group);

        Livewire::actingAs($owner)
            ->test(Groups\Show::class, ['group' => $group])
            ->call('deleteGroup');

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('matches', ['group_id' => $group->id]);

        // Üye grubu silemez
        $owner2 = User::factory()->create();
        $group2 = $this->makeGroup($owner2);
        $member = $this->addMember($group2);
        Livewire::actingAs($member->user)->test(Groups\Show::class, ['group' => $group2])
            ->call('deleteGroup')->assertStatus(403);
    }

    public function test_kullanici_profilinden_kendi_pozisyon_ayagini_duzenler(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $member = $this->addMember($group);

        // Üye kendi oyuncu kaydını düzenler
        Livewire::actingAs($member->user)
            ->test(\App\Livewire\Profile\FieldProfile::class)
            ->call('edit', $member->id)
            ->call('togglePosition', 'OS')  // varsayılan OS'u kaldır
            ->call('togglePosition', 'FV')  // FV ekle
            ->set('editFoot', 'left')
            ->set('editNumber', 7)
            ->call('save')
            ->assertHasNoErrors();

        $member->refresh();
        $this->assertSame(['FV'], $member->positions);
        $this->assertSame('left', $member->foot);
        $this->assertSame(7, $member->shirt_number);

        // Başkasının oyuncusunu düzenleyemez
        $own = $group->playerFor($owner);
        Livewire::actingAs($member->user)
            ->test(\App\Livewire\Profile\FieldProfile::class)
            ->call('edit', $own->id)
            ->assertStatus(404);
    }

    public function test_mac_sonu_performans_puani_nihai_puana_yansir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');

        // Sonucu gir → performans (MVP) penceresi açılır
        Livewire::actingAs($owner)->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 1)->set('teamBScore', 0)->call('saveResult')->assertHasNoErrors();

        // Owner, friend'e performans 8 verir (güncellenebilir)
        Livewire::actingAs($owner)->test(Matches\Show::class, ['match' => $match])
            ->call('ratePerformance', $friend->id, 6)
            ->call('ratePerformance', $friend->id, 8);

        $this->assertSame(1, $match->performanceRatings()->where('player_id', $friend->id)->count());
        $this->assertSame(8, $match->performanceRatings()->where('player_id', $friend->id)->value('score'));

        $friend->refresh();
        $this->assertEqualsWithDelta(8.0, $friend->matchPerformance(), 0.01);
        // OVR (puansız) = 5.0 → nihai = 5×0.8 + 8×0.2 = 5.6, form ▲0.6
        $this->assertEqualsWithDelta(5.6, $friend->displayRating(), 0.01);
        $this->assertEqualsWithDelta(0.6, $friend->formDelta(), 0.01);

        // Kendine performans veremez
        Livewire::actingAs($owner)->test(Matches\Show::class, ['match' => $match])
            ->call('ratePerformance', $ownPlayer->id, 9)->assertStatus(403);
    }

    public function test_puanlama_arti_eksi_butonu_clampler(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $friend = $this->addMember($group);

        Livewire::actingAs($owner)->test(Groups\Rate::class, ['group' => $group])
            ->call('select', $friend->id)
            ->call('adjust', 'hiz', 3)   // 5 + 3 = 8
            ->assertSet('scores.hiz', 8)
            ->call('adjust', 'hiz', 10)  // 8 + 10 → 10 (üst sınır)
            ->assertSet('scores.hiz', 10)
            ->call('adjust', 'hiz', -20) // → 1 (alt sınır)
            ->assertSet('scores.hiz', 1);
    }

    public function test_baska_grubun_sayfasina_ve_macina_erisilemez(): void
    {
        // İzolasyon — 1. katman: mount'taki üyelik kapısı (abort_unless isMember, 403)
        $ownerA = User::factory()->create();
        $groupA = $this->makeGroup($ownerA);
        $matchA = $this->makeMatch($groupA);

        // ownerB, A grubunun üyesi değil (kendi grubu var)
        $ownerB = User::factory()->create();
        $this->makeGroup($ownerB);

        // HTTP istekleri: yabancı kullanıcı A'nın sayfalarını göremez
        $this->actingAs($ownerB)->get(route('groups.show', $groupA))->assertForbidden();
        $this->actingAs($ownerB)->get(route('groups.rate', $groupA))->assertForbidden();
        $this->actingAs($ownerB)->get(route('groups.stats', $groupA))->assertForbidden();
        $this->actingAs($ownerB)->get(route('matches.show', $matchA))->assertForbidden();

        // Livewire mount kapısı da doğrudan erişimi engeller
        Livewire::actingAs($ownerB)->test(Matches\Show::class, ['match' => $matchA])->assertStatus(403);
        Livewire::actingAs($ownerB)->test(Groups\Show::class, ['group' => $groupA])->assertStatus(403);
    }

    public function test_capraz_grup_id_ile_oyuncu_yonetilemez(): void
    {
        // İzolasyon — 2. katman: ilişki-traversal kapsama (başka grubun ID'si bulunamaz)
        $ownerA = User::factory()->create();
        $groupA = $this->makeGroup($ownerA);
        $matchA = $this->makeMatch($groupA);

        $ownerB = User::factory()->create();
        $groupB = $this->makeGroup($ownerB);
        $playerB = $this->addMember($groupB); // B grubunun oyuncusu

        // ownerA kendi grubunun adminidir (403 kapısını geçer) ama B'nin oyuncu ID'siyle
        // aksiyon denerse, $this->group->players()->findOrFail() zinciri onu bulamaz → 404
        $this->assertThrows(
            fn () => Livewire::actingAs($ownerA)
                ->test(Groups\Show::class, ['group' => $groupA])
                ->call('editPositions', $playerB->id),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );

        // Maç aksiyonunda da çapraz-grup oyuncu ID'si ebeveynden çözülemez → 404
        $this->assertThrows(
            fn () => Livewire::actingAs($ownerA)
                ->test(Matches\Show::class, ['match' => $matchA])
                ->call('setPlayerRsvp', $playerB->id, 'going'),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );
    }

    public function test_rozetler_mac_verisinden_hesaplanir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $p1 = $group->playerFor($owner);
        $p2 = $this->addMember($group);

        // Tamamlanmış maç: A 5–2 B, MVP penceresi kapanmış (mvp sayılsın)
        $match = $group->matches()->create([
            'created_by' => $owner->id,
            'title' => 'Final',
            'starts_at' => now()->subDay(),
            'capacity' => 14,
            'status' => 'completed',
            'team_a_score' => 5,
            'team_b_score' => 2,
            'mvp_closes_at' => now()->subHour(),
        ]);
        $match->rsvps()->create(['player_id' => $p1->id, 'status' => 'going', 'team' => 'A']);
        $match->rsvps()->create(['player_id' => $p2->id, 'status' => 'going', 'team' => 'B']);
        $match->goals()->create(['player_id' => $p1->id, 'count' => 3]); // hat-trick
        $match->mvpVotes()->create(['voter_id' => $owner->id, 'player_id' => $p1->id]);

        $badges = app(PlayerBadges::class);
        $stats = $badges->statsForPlayer($p1);

        $this->assertSame(1, $stats['played']);
        $this->assertSame(1, $stats['win']);
        $this->assertSame(3, $stats['goals']);
        $this->assertSame(3, $stats['best_match_goals']);
        $this->assertSame(1, $stats['mvp']);

        $earned = collect($badges->evaluate($stats))->where('earned', true)->pluck('key');
        $this->assertContains('first_goal', $earned);
        $this->assertContains('hat_trick', $earned);
        $this->assertContains('mvp', $earned);
        $this->assertContains('first_match', $earned);
        $this->assertNotContains('scorer', $earned);  // 10 gol eşiği geçilmedi
        $this->assertNotContains('veteran', $earned);  // 50 maç eşiği geçilmedi
    }

    public function test_yeni_rozetler_seri_duvar_mukemmel_hesaplanir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $keeper = $group->playerFor($owner);
        $keeper->update(['positions' => ['KL']]);
        $rakip = $this->addMember($group);

        // Üst üste 3 galibiyet, hepsi gol yemeden (A takımı kalecisi)
        foreach ([1, 2, 3] as $i) {
            $match = $group->matches()->create([
                'created_by' => $owner->id,
                'title' => "Maç {$i}",
                'starts_at' => now()->subDays(10 - $i),
                'capacity' => 14,
                'status' => 'completed',
                'team_a_score' => 2,
                'team_b_score' => 0,
                'mvp_closes_at' => now()->subHour(),
            ]);
            $match->rsvps()->create(['player_id' => $keeper->id, 'status' => 'going', 'team' => 'A']);
            $match->rsvps()->create(['player_id' => $rakip->id, 'status' => 'going', 'team' => 'B']);
        }

        // Son maçta 9+ performans ortalaması (Mükemmel Maç)
        $group->matches()->latest('starts_at')->first()
            ->performanceRatings()->create(['rater_id' => $rakip->user_id, 'player_id' => $keeper->id, 'score' => 9]);

        $badges = app(PlayerBadges::class);
        $stats = $badges->statsForPlayer($keeper);

        $this->assertSame(3, $stats['win']);
        $this->assertSame(3, $stats['win_streak']);
        $this->assertSame(3, $stats['clean_sheets']);
        $this->assertEquals(9.0, $stats['best_match_perf']);

        $earned = collect($badges->evaluate($stats))->where('earned', true)->pluck('key');
        $this->assertContains('win_streak', $earned);   // Seri: üst üste 3
        $this->assertContains('wall', $earned);         // Duvar: gol yemeden
        $this->assertContains('perfect_match', $earned); // Mükemmel Maç: 9+
        $this->assertNotContains('winner', $earned);    // Galip: 10 galibiyet henüz yok

        // Rakip kaleci değil ve hep kaybetti: takım rozetleri yok
        $rakipEarned = collect($badges->evaluate($badges->statsForPlayer($rakip)))->where('earned', true)->pluck('key');
        $this->assertNotContains('wall', $rakipEarned);
        $this->assertNotContains('win_streak', $rakipEarned);
    }

    public function test_havuzdan_profile_gidilir_ve_kafa_kafaya_kiyaslanir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $p1 = $group->playerFor($owner);
        $p2 = $this->addMember($group);

        // Havuzdaki oyuncu adı profile linkli
        $this->actingAs($owner)->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee(route('groups.player', [$group, $p2]));

        // Kafa kafaya: iki isim ve VS görünür
        Livewire::actingAs($owner)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $p1])
            ->set('compareId', $p2->id)
            ->assertSee('VS')
            ->assertSee($p2->name)
            ->assertSee('EN UZUN SERİ');

        // Kendisiyle kıyas sıfırlanır
        Livewire::actingAs($owner)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $p1])
            ->set('compareId', $p1->id)
            ->assertSet('compareId', null);

        // Çapraz grup: başka grubun oyuncusuyla kıyas → 404
        $foreign = $this->makeGroup(User::factory()->create());
        $foreignPlayer = $foreign->players()->first();

        $this->assertThrows(
            fn () => Livewire::actingAs($owner)
                ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $p1])
                ->set('compareId', $foreignPlayer->id),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );
    }

    public function test_takim_kimyasi_ikili_kazanma_oranini_hesaplar(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $p1 = $group->playerFor($owner);
        $p2 = $this->addMember($group);
        $p3 = $this->addMember($group);

        // p1+p2 hep A takımında: 3 maçta 2 galibiyet 1 mağlubiyet → %67
        foreach ([[3, 1], [2, 0], [0, 1]] as $i => [$a, $b]) {
            $match = $group->matches()->create([
                'created_by' => $owner->id, 'title' => "Maç {$i}",
                'starts_at' => now()->subDays(9 - $i), 'capacity' => 14,
                'status' => 'completed', 'team_a_score' => $a, 'team_b_score' => $b,
            ]);
            $match->rsvps()->create(['player_id' => $p1->id, 'status' => 'going', 'team' => 'A']);
            $match->rsvps()->create(['player_id' => $p2->id, 'status' => 'going', 'team' => 'A']);
            $match->rsvps()->create(['player_id' => $p3->id, 'status' => 'going', 'team' => 'B']);
        }

        $pairs = app(\App\Services\TeamChemistry::class)->pairsForGroup($group);

        $this->assertCount(1, $pairs, 'Sadece 3+ ortak maçlı ikili listelenir (p3 kimseyle 3 maç aynı takımda değil... p3 B takımında yalnız)');
        $this->assertSame(3, $pairs[0]['together']);
        $this->assertSame(2, $pairs[0]['wins']);
        $this->assertSame(67, $pairs[0]['rate']);

        // Profilde en uyumlu ortak görünür
        $this->actingAs($owner)->get(route('groups.player', [$group, $p1]))
            ->assertOk()
            ->assertSee('En uyumlu ortağı')
            ->assertSee($p2->name);

        // İstatistik sayfasında kimya kartı
        $this->actingAs($owner)->get(route('groups.stats', $group))
            ->assertOk()
            ->assertSee('Takım Kimyası')
            ->assertSee('%67');
    }

    public function test_oyuncu_karti_ve_foto_yukleme(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $p1 = $group->playerFor($owner);
        $other = $this->addMember($group);

        // Kart profilde görünür (4 istatistik etiketi)
        $this->actingAs($owner)->get(route('groups.player', [$group, $p1]))
            ->assertOk()
            ->assertSee('GENEL')->assertSee('HIZ')->assertSee('ŞUT')->assertSee('PAS')->assertSee('DEF');

        // Kendi kartına fotoğraf yükler
        $file = \Illuminate\Http\UploadedFile::fake()->image('kart.jpg', 400, 400);
        Livewire::actingAs($owner)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $p1])
            ->set('photo', $file)
            ->assertHasNoErrors();

        $p1->refresh();
        $this->assertNotNull($p1->photo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($p1->photo_path);

        // Sunucuda kare kırpılıp küçültülür (ekranı kaplamasın): 400x400 girdi → 400x400 jpeg, 512 üstü olamaz
        [$w, $h] = getimagesizefromstring(\Illuminate\Support\Facades\Storage::disk('public')->get($p1->photo_path));
        $this->assertSame($w, $h, 'Kart fotoğrafı kare olmalı');
        $this->assertLessThanOrEqual(512, $w);

        // Başkasının kartına yükleyemez (profil sayfası onun, foto başkasının olur → 403)
        Livewire::actingAs($owner)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $other])
            ->set('photo', \Illuminate\Http\UploadedFile::fake()->image('sahte.jpg'))
            ->assertStatus(403);

        $this->assertNull($other->refresh()->photo_path);
    }

    public function test_havuzda_rozet_ikonu_ve_nitelik_etiketi_gorunur(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $p1 = $group->playerFor($owner);
        $m1 = $this->addMember($group);

        // p1 bir maç oynadı → 🐣 İlk Maç rozeti
        $match = $group->matches()->create([
            'created_by' => $owner->id, 'title' => 'Maç', 'starts_at' => now()->subDay(),
            'capacity' => 14, 'status' => 'completed', 'team_a_score' => 1, 'team_b_score' => 0,
        ]);
        $match->rsvps()->create(['player_id' => $p1->id, 'status' => 'going', 'team' => 'A']);

        // p1'e nitelik onayı
        $p1->traitEndorsements()->create(['trait_key' => 'maestro', 'endorser_id' => $m1->user_id]);

        $this->actingAs($owner)->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('Maestro')  // nitelik etiketi
            ->assertSee('🐣');      // rozet ikonu
    }

    public function test_performans_puanlari_kaydet_butonuyla_yazilir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match = $group->matches()->create([
            'created_by' => $owner->id, 'title' => 'Dünkü maç', 'starts_at' => now()->subDay(),
            'capacity' => 14, 'status' => 'completed', 'team_a_score' => 2, 'team_b_score' => 1,
            'mvp_closes_at' => now()->addDays(5),
        ]);
        $match->rsvps()->create(['player_id' => $ownPlayer->id, 'status' => 'going', 'team' => 'A']);
        $match->rsvps()->create(['player_id' => $friend->id, 'status' => 'going', 'team' => 'B']);

        // +/- yerelde birikir, Kaydet'e basmadan DB'ye yazılmaz
        $c = Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('adjustPerf', $friend->id, 1)
            ->call('adjustPerf', $friend->id, 1)
            ->call('adjustPerf', $friend->id, 1); // 5 → 8
        $this->assertSame(0, $match->performanceRatings()->count());

        $c->call('savePerformance')->assertSet('perfSaved', true);
        $this->assertSame(8, (int) $match->performanceRatings()
            ->where('rater_id', $owner->id)->where('player_id', $friend->id)->value('score'));

        // Tekrar açılınca mevcut puan yüklenir (5 değil 8'den devam)
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->assertSet('perfScores', [$friend->id => 8]);
    }

    public function test_nitelik_onayi_toggle_sinir_ve_esik_bildirimi(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $target = $group->playerFor($owner);
        $m1 = $this->addMember($group);
        $m2 = $this->addMember($group);
        $m3 = $this->addMember($group);

        // Seçim yerelde birikir, Kaydet'e basmadan DB'ye yazılmaz
        $c = Livewire::actingAs($m1->user)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'maestro');
        $this->assertSame(0, $target->traitEndorsements()->count());

        // Kaydet → yazılır; seçim kaldırılıp tekrar kaydedilince silinir
        $c->call('saveTraits');
        $this->assertSame(1, $target->traitEndorsements()->count());
        $c->call('toggleTraitSelection', 'maestro')->call('saveTraits');
        $this->assertSame(0, $target->traitEndorsements()->count());

        // Kişi başı en fazla 3 nitelik: 4. seçimde uyarı, seçilmez
        Livewire::actingAs($m1->user)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'maestro')
            ->call('toggleTraitSelection', 'buz_adam')
            ->call('toggleTraitSelection', 'joker')
            ->call('toggleTraitSelection', 'fuze')
            ->assertSet('traitNotice', fn ($v) => str_contains((string) $v, 'en fazla'))
            ->call('saveTraits');
        $this->assertSame(3, $target->traitEndorsements()->where('endorser_id', $m1->user_id)->count());

        // Kendine onay yok
        Livewire::actingAs($owner)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'maestro')
            ->assertStatus(403);

        // 3. onayda tek push gider (m1 zaten maestro onayladı; m2 + m3 ile 3 olur)
        Livewire::actingAs($m2->user)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'maestro')->call('saveTraits');
        Notification::assertNotSentTo($owner, MatchPushNotification::class,
            fn ($n) => str_contains($n->body, 'Maestro'));

        Livewire::actingAs($m3->user)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'maestro')->call('saveTraits');
        Notification::assertSentTo($owner, MatchPushNotification::class,
            fn ($n) => str_contains($n->body, 'Maestro') && str_contains($n->body, '3 onaya'));

        // Bilinmeyen nitelik anahtarı reddedilir
        Livewire::actingAs($m1->user)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'uydurma_nitelik')
            ->assertStatus(400);
    }

    public function test_forma_golu_skora_etki_etmez_ve_rozet_verir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);
        $yabanci = $this->addMember($group); // kadroda değil

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 3)
            ->set('teamBScore', 2)
            ->set('goals', [$friend->id => 2])
            ->set('formaGoalPlayerId', $friend->id)
            ->call('saveResult')
            ->assertHasNoErrors();

        $match->refresh();

        // Skor ve gol istatistiği forma golünden etkilenmez
        $this->assertSame($friend->id, $match->forma_goal_player_id);
        $this->assertSame(3, $match->team_a_score);
        $this->assertSame(2, $match->team_b_score);
        $this->assertSame(2, (int) $match->goals()->where('player_id', $friend->id)->value('count'));
        $this->assertSame(1, $match->goals()->count(), 'Forma golü ayrı bir gol kaydı oluşturmamalı');

        // Rozet: forma golü sayılır
        $badges = app(PlayerBadges::class);
        $stats = $badges->statsForPlayer($friend);
        $this->assertSame(1, $stats['forma_goals']);
        $earned = collect($badges->evaluate($stats))->where('earned', true)->pluck('key');
        $this->assertContains('forma_golu', $earned);
        $this->assertNotContains('forma_ustasi', $earned); // 5 eşiği geçilmedi

        // Kadroda olmayan oyuncu forma golü olarak kaydedilemez
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match->refresh()])
            ->set('formaGoalPlayerId', $yabanci->id)
            ->call('saveResult');
        $this->assertNull($match->refresh()->forma_goal_player_id);

        // Maç sayfasında görünür
        $match->update(['forma_goal_player_id' => $friend->id]);
        $this->actingAs($owner)->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('Forma golü')
            ->assertSee($friend->name);
    }

    public function test_mac_karti_ve_gelisim_grafigi(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $p1 = $group->playerFor($owner);
        $friend = $this->addMember($group);

        // İki tamamlanmış maç, performans puanlarıyla (grafik için en az 2 nokta gerekli)
        foreach ([[3, 1, 7], [2, 2, 9]] as $i => [$a, $b, $puan]) {
            $match = $group->matches()->create([
                'created_by' => $owner->id, 'title' => "Maç {$i}", 'starts_at' => now()->subDays(5 - $i),
                'capacity' => 14, 'status' => 'completed', 'team_a_score' => $a, 'team_b_score' => $b,
                'mvp_closes_at' => now()->subHour(),
            ]);
            $match->rsvps()->create(['player_id' => $p1->id, 'status' => 'going', 'team' => 'A']);
            $match->rsvps()->create(['player_id' => $friend->id, 'status' => 'going', 'team' => 'B']);
            $match->performanceRatings()->create(['rater_id' => $friend->user_id, 'player_id' => $p1->id, 'score' => $puan]);
        }

        // Gelişim grafiği: 2 nokta, eskiden yeniye sıralı
        $history = $p1->performanceHistory();
        $this->assertCount(2, $history);
        $this->assertSame(7.0, $history[0]['score']);
        $this->assertSame(9.0, $history[1]['score']);
        $this->assertTrue($history[0]['date']->lt($history[1]['date']), 'Grafik eskiden yeniye sıralı olmalı');

        $this->actingAs($owner)->get(route('groups.player', [$group, $p1]))
            ->assertOk()
            ->assertSee('FORM GRAFİĞİ', false);

        // Maç kartı: tamamlanmış maçta veri hazırlanır ve sayfada görünür
        $last = $group->matches()->where('status', 'completed')->latest('starts_at')->first();
        $last->goals()->create(['player_id' => $friend->id, 'count' => 2]);

        $this->actingAs($owner)->get(route('matches.show', $last))
            ->assertOk()
            ->assertSee('Maç Kartı')
            ->assertSee('macKarti', false)
            ->assertSee($group->name);

        // Planlı maçta kart yok
        $upcoming = $this->makeMatch($group);
        $this->actingAs($owner)->get(route('matches.show', $upcoming))
            ->assertOk()
            ->assertDontSee('Maç Kartı');
    }

    public function test_olumsuz_nitelikler_ayri_limit_esik_ve_bildirimsiz(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $target = $group->playerFor($owner);
        $m1 = $this->addMember($group);
        $m2 = $this->addMember($group);
        $m3 = $this->addMember($group);

        // Olumsuzlarda ayrı ve daha düşük limit (2): 3.'de uyarı verir, seçilmez
        Livewire::actingAs($m1->user)
            ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
            ->call('toggleTraitSelection', 'kazma')
            ->call('toggleTraitSelection', 'agir_abi')
            ->call('toggleTraitSelection', 'cam_adam')
            ->assertSet('traitNotice', fn ($v) => str_contains((string) $v, 'takılma'))
            // Olumlu limiti ayrı işler: 3 olumlu hâlâ seçilebilir
            ->call('toggleTraitSelection', 'maestro')
            ->call('toggleTraitSelection', 'joker')
            ->call('toggleTraitSelection', 'beton')
            ->call('saveTraits');

        $kayitlar = $target->traitEndorsements()->where('endorser_id', $m1->user_id)->pluck('trait_key');
        $this->assertCount(5, $kayitlar, '3 olumlu + 2 olumsuz kaydedilmeli');
        $this->assertContains('kazma', $kayitlar);
        $this->assertNotContains('cam_adam', $kayitlar);

        // Eşiğin altındaki takılma profilde GÖRÜNMEZ (tek kişi yapıştıramaz)
        $this->actingAs($owner)->get(route('groups.player', [$group, $target]))
            ->assertOk()
            ->assertSee('Maestro')
            ->assertDontSee('Kazma');

        // 3. onayla eşik dolar → görünür olur, ama bildirim GİTMEZ
        foreach ([$m2, $m3] as $uye) {
            Livewire::actingAs($uye->user)
                ->test(Groups\PlayerProfile::class, ['group' => $group, 'player' => $target])
                ->call('toggleTraitSelection', 'kazma')
                ->call('saveTraits');
        }

        $this->actingAs($owner)->get(route('groups.player', [$group, $target]))
            ->assertOk()
            ->assertSee('Kazma')
            ->assertSee('TAKILMALAR');

        Notification::assertNotSentTo($owner, MatchPushNotification::class,
            fn ($n) => str_contains($n->body, 'Kazma'));

        // Havuz listesine takılmalar sızmaz (olumlu etiket gösterimi ayrı testte)
        $this->actingAs($owner)->get(route('groups.show', $group))
            ->assertOk()
            ->assertDontSee('Kazma');
    }

    public function test_oyuncu_profili_izolasyon_korunur(): void
    {
        $ownerA = User::factory()->create();
        $groupA = $this->makeGroup($ownerA);
        $playerA = $groupA->playerFor($ownerA);

        $ownerB = User::factory()->create();
        $groupB = $this->makeGroup($ownerB);
        $playerB = $groupB->playerFor($ownerB);

        // Üye olmayan A grubundaki bir oyuncunun profilini göremez (mount kapısı, 403)
        $this->actingAs($ownerB)->get(route('groups.player', [$groupA, $playerA]))->assertForbidden();

        // A admini, B'nin oyuncu ID'siyle A grubu üzerinden profile erişemez (traversal, 404)
        $this->assertThrows(
            fn () => Livewire::actingAs($ownerA)
                ->test(Groups\PlayerProfile::class, ['group' => $groupA, 'player' => $playerB]),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );

        // Kendi grubundaki oyuncu profili normal açılır
        $this->actingAs($ownerA)->get(route('groups.player', [$groupA, $playerA]))->assertOk();
    }

    public function test_misafir_oyuncu_puanlanmaz_ve_sabit_puanla_gelir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $guest = $group->players()->create(['name' => 'Misafir Ali', 'positions' => []]); // user_id null

        // Misafir: sabit 6.5, hep görünür, form yansımaz
        $this->assertTrue($guest->isGuest());
        $this->assertSame(6.5, $guest->overall());
        $this->assertSame(6.5, $guest->displayRating());
        $this->assertTrue($guest->overallIsPublic());
        $this->assertNull($guest->matchPerformance());
        $this->assertNull($guest->formDelta());

        // Puanlama akışında misafir seçilemez/kaydedilemez (403)
        Livewire::actingAs($owner)
            ->test(Groups\Rate::class, ['group' => $group])
            ->call('select', $guest->id)
            ->assertStatus(403);
    }

    public function test_push_bildirimleri_dogru_olaylarda_gider(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $memberPlayer = $this->addMember($group);
        $member = $memberPlayer->user;

        // 1) Yeni maç → üyeye gider, açan kişiye gitmez
        Livewire::actingAs($owner)
            ->test(Groups\Show::class, ['group' => $group])
            ->set('title', 'Perşembe 21:00 maçı')
            ->set('starts_at', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->set('capacity', 10)
            ->call('createMatch');

        Notification::assertSentTo($member, MatchPushNotification::class, fn ($n) => str_contains($n->title, 'Yeni maç'));
        Notification::assertNotSentTo($owner, MatchPushNotification::class);

        $match = $group->matches()->first();
        $match->setRsvp($group->playerFor($owner), 'going');
        $match->setRsvp($memberPlayer, 'going');

        // 2) Kadro ilk kez oylamaya sunulunca gider; alternatif gezinmek (voting→voting) tekrar göndermez
        Notification::fake();
        $this->actingAs($owner);
        $match->refresh()->applySquad([$group->playerFor($owner)->id], [$memberPlayer->id]);
        Notification::assertSentTo($member, MatchPushNotification::class, fn ($n) => str_contains($n->title, 'Kadro'));

        Notification::fake();
        $match->refresh()->applySquad([$memberPlayer->id], [$group->playerFor($owner)->id]);
        Notification::assertNothingSent();
    }

    public function test_mac_hatirlatmasi_bir_kez_gonderilir(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $member = $this->addMember($group)->user;

        // 24 saat penceresinde bir maç
        $group->matches()->create([
            'created_by' => $owner->id,
            'title' => 'Yarınki maç',
            'starts_at' => now()->addHours(20),
            'capacity' => 10,
        ]);

        app(PushNotifier::class)->sendDueReminders();
        Notification::assertSentTo($member, MatchPushNotification::class, fn ($n) => str_contains($n->title, 'yaklaşıyor'));

        // İkinci çalıştırma aynı maç için tekrar göndermez (reminder_sent_at işaretli)
        Notification::fake();
        app(PushNotifier::class)->sendDueReminders();
        Notification::assertNothingSent();
    }

    public function test_gecmis_macin_kadro_oylamasi_bekleyenlerde_gorunmez(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        // Geçmişte kalmış, kadrosu hâlâ "voting" durumunda maç
        $past = $group->matches()->create([
            'created_by' => $owner->id,
            'title' => 'Geçen haftaki maç',
            'starts_at' => now()->subDays(3),
            'capacity' => 14,
            'squad_status' => 'voting',
        ]);
        $past->rsvps()->create(['player_id' => $ownPlayer->id, 'status' => 'going', 'team' => 'A']);
        $past->rsvps()->create(['player_id' => $friend->id, 'status' => 'going', 'team' => 'B']);

        // Gelecekteki maç oylamada → görünmeli
        $future = $this->makeMatch($group);
        $future->setRsvp($ownPlayer, 'going');
        $future->setRsvp($friend, 'going');
        $future->applySquad([$ownPlayer->id], [$friend->id]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Geçen haftaki maç')
            ->assertSee($future->title);
    }

    public function test_tanitim_turu_ilk_giriste_gorunur_ve_isaretlenir(): void
    {
        $owner = User::factory()->create();
        $this->makeGroup($owner);

        // İlk giriş: modal içerik + Rehber butonu sayfada
        $this->assertNull($owner->tutorial_seen_at);
        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hoş Geldin')
            ->assertSee('Rehber')
            ->assertSee('Dengeli Kadro');

        // Kapatınca işaretlenir, bir daha otomatik açılmaz (auto-open false olur)
        Livewire::actingAs($owner)
            ->test(\App\Livewire\Dashboard::class)
            ->call('markTutorialSeen');

        $seenAt = $owner->refresh()->tutorial_seen_at;
        $this->assertNotNull($seenAt);

        // İkinci çağrı tarihi değiştirmez (idempotent)
        Livewire::actingAs($owner)
            ->test(\App\Livewire\Dashboard::class)
            ->call('markTutorialSeen');
        $this->assertTrue($seenAt->equalTo($owner->refresh()->tutorial_seen_at));
    }

    public function test_grup_hizli_erisim_cubugu_her_sayfada(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $myPlayer = $group->playerFor($owner);
        $match = $this->makeMatch($group); // gelecek tarihli, scheduled

        $statsUrl = route('groups.stats', $group);
        $profilUrl = route('groups.player', [$group, $myPlayer]);
        $macUrl = route('matches.show', $match);

        $kehanetUrl = route('groups.kehanet', $group);

        // Grup bağlamındaki her sayfada dört bağlantı da var
        foreach ([route('groups.show', $group), $statsUrl, route('groups.rate', $group), $profilUrl, $macUrl, $kehanetUrl] as $url) {
            $this->actingAs($owner)->get($url)
                ->assertOk()
                ->assertSee($statsUrl)
                ->assertSee($profilUrl)
                ->assertSee($macUrl)
                ->assertSee($kehanetUrl);
        }

        // Yaklaşan maç yoksa buton "Maç yok" olarak pasifleşir
        $match->update(['status' => 'cancelled']);
        $this->actingAs($owner)->get($statsUrl)
            ->assertOk()
            ->assertSee('Maç yok')
            ->assertDontSee($macUrl);
    }

    public function test_kehanet_kupon_bakiye_ve_sonuclanma(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');
        $match->applySquad([$ownPlayer->id], [$friend->id]);

        // Sayfaya girince başlangıç Çim'i yüklenir
        $c = Livewire::actingAs($owner)->test(Groups\Kehanet::class, ['group' => $group]);
        $owner->refresh();
        $this->assertSame(\App\Support\Kehanet::STARTING_BALANCE, $owner->cim_balance);

        // Kupon: Turuncu kazanır
        $c->set("selection.{$match->id}-winner", 'A')
            ->set("stake.{$match->id}-winner", 30)
            ->call('bet', $match->id, 'winner');

        $kupon = \App\Models\Prediction::where('user_id', $owner->id)->firstOrFail();
        $this->assertSame(30, $kupon->stake);

        // Kupon sekmesi: kadro listesi + kendini ayırt etme + kilit durumu
        $this->actingAs($owner)->get(route('groups.kehanet', $group))
            ->assertOk()
            ->assertSee('Turuncu')
            ->assertSee($friend->name)
            ->assertSee('SEN')
            ->assertSee('Kuponun kesinleşti');

        // Bekleyen tahminler "Kuponlarım" sekmesinde, seçim etiketiyle
        Livewire::actingAs($owner)
            ->test(Groups\Kehanet::class, ['group' => $group])
            ->call('setTab', 'kuponlarim')
            ->assertSee('Bekleyen Tahminlerin')
            ->assertSee('Turuncu');

        $this->assertGreaterThan(1.0, (float) $kupon->odds);
        $this->assertSame(\App\Support\Kehanet::STARTING_BALANCE - 30, $owner->refresh()->cim_balance);

        // Kupon kesindir: aynı market'e ikinci kupon yapılamaz, bakiye değişmez
        $bakiyeOnce = $owner->refresh()->cim_balance;
        $c->set("selection.{$match->id}-winner", 'B')
            ->set("stake.{$match->id}-winner", 5)
            ->call('bet', $match->id, 'winner')
            ->assertSet('notice', fn ($v) => str_contains((string) $v, 'değiştirilemez'));

        $this->assertSame($bakiyeOnce, $owner->refresh()->cim_balance, 'Reddedilen kupon bakiyeye dokunmamalı');
        $this->assertSame(1, \App\Models\Prediction::where('market_key', 'winner')->count());
        $this->assertSame('A', \App\Models\Prediction::where('market_key', 'winner')->value('selection'));

        // Manuel olay kuponu: gerginliği friend yaşayacak
        $c->set("selection.{$match->id}-gerginlik", (string) $friend->id)
            ->set("stake.{$match->id}-gerginlik", 10)
            ->call('bet', $match->id, 'gerginlik');

        // Skor girilir → otomatik market sonuçlanır, manuel olan beklemede kalır
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 4)->set('teamBScore', 1)
            ->call('saveResult');

        $kupon->refresh();
        $this->assertSame('won', $kupon->status);
        $this->assertSame((int) round(30 * (float) $kupon->odds), $kupon->payout);

        $gerginlikKuponu = \App\Models\Prediction::where('market_key', 'gerginlik')->firstOrFail();
        $this->assertSame('pending', $gerginlikKuponu->status, 'Başkan işaretlemeden sonuçlanmamalı');

        // Başkan olayı işaretler → kupon sonuçlanır
        Livewire::actingAs($owner)
            ->test(Groups\Kehanet::class, ['group' => $group])
            ->set("eventPick.{$match->id}-gerginlik", (string) $friend->id)
            ->call('saveEvents', $match->id);

        $this->assertSame('won', $gerginlikKuponu->refresh()->status);

        // Yetersiz bakiyeyle kupon yapılamaz
        $owner->forceFill(['cim_balance' => 3])->save();
        $ikinciMac = $this->makeMatch($group);
        Livewire::actingAs($owner)
            ->test(Groups\Kehanet::class, ['group' => $group])
            ->set("selection.{$ikinciMac->id}-winner", 'A')
            ->set("stake.{$ikinciMac->id}-winner", 100)
            ->call('bet', $ikinciMac->id, 'winner')
            ->assertSet('notice', fn ($v) => str_contains((string) $v, 'Yeterli Çim yok'));

        // Başka grubun üyesi kehanet sayfasına giremez
        $yabanci = User::factory()->create();
        $this->makeGroup($yabanci);
        $this->actingAs($yabanci)->get(route('groups.kehanet', $group))->assertForbidden();
    }

    public function test_kehanet_kendine_kupon_yasak_ve_kadro_degisince_iade(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);
        $ucuncu = $this->addMember($group);

        $match = $this->makeMatch($group);
        foreach ([$ownPlayer, $friend, $ucuncu] as $p) {
            $match->setRsvp($p, 'going');
        }

        $c = Livewire::actingAs($owner)->test(Groups\Kehanet::class, ['group' => $group]);

        // Kendi hakkında oyuncu tahmini yapılamaz
        $c->set("selection.{$match->id}-scorer", (string) $ownPlayer->id)
            ->set("stake.{$match->id}-scorer", 20)
            ->call('bet', $match->id, 'scorer')
            ->assertSet('notice', fn ($v) => str_contains((string) $v, 'Kendinle'));
        $this->assertSame(0, \App\Models\Prediction::count());

        // Kendi adı seçenek listesinde de görünmez
        $this->actingAs($owner)->get(route('groups.kehanet', $group))
            ->assertOk()
            ->assertSee($friend->name);

        // Başkası hakkında serbest — ve bakiye ekranda anında düşer
        $baslangic = $owner->refresh()->cim_balance;
        $c->set("selection.{$match->id}-scorer", (string) $friend->id)
            ->set("stake.{$match->id}-scorer", 20)
            ->call('bet', $match->id, 'scorer')
            ->assertSee(number_format($baslangic - 20)); // güncel bakiye render edilir
        $this->assertSame($baslangic - 20, $owner->refresh()->cim_balance);

        // Takım market'inde kısıt yok
        $c->set("selection.{$match->id}-winner", 'A')
            ->set("stake.{$match->id}-winner", 10)
            ->call('bet', $match->id, 'winner');
        $this->assertSame(2, \App\Models\Prediction::where('status', 'pending')->count());

        // Kadro değişir: friend gelmiyor → onun üzerine kupon iade edilir, takım kuponu kalır
        $oncekiBakiye = $owner->refresh()->cim_balance;
        $match->setRsvp($friend, 'not_going');

        $oyuncuKuponu = \App\Models\Prediction::where('market_key', 'scorer')->firstOrFail();
        $takimKuponu = \App\Models\Prediction::where('market_key', 'winner')->firstOrFail();

        $this->assertSame('void', $oyuncuKuponu->refresh()->status, 'Kadrodan çıkan oyuncunun kuponu iade edilmeli');
        $this->assertSame('pending', $takimKuponu->refresh()->status, 'Takım kuponu etkilenmemeli');
        $this->assertSame($oncekiBakiye + 20, $owner->refresh()->cim_balance);
    }

    public function test_kehanet_kombine_skor_nabiz_ve_hareket_gecmisi(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');
        $match->applySquad([$ownPlayer->id], [$friend->id]);

        $c = Livewire::actingAs($owner)->test(Groups\Kehanet::class, ['group' => $group]);
        $baslangic = $owner->refresh()->cim_balance;

        // Skor tam tahmini: yüksek oran vermeli
        $c->set("scorePick.{$match->id}-a", 4)
            ->set("scorePick.{$match->id}-b", 2)
            ->set("stake.{$match->id}-exact_score", 10)
            ->call('betScore', $match->id);

        $skorKuponu = \App\Models\Prediction::where('market_key', 'exact_score')->firstOrFail();
        $this->assertSame('4-2', $skorKuponu->selection);
        $this->assertGreaterThan(3.0, (float) $skorKuponu->odds, 'Tam skor oranı yüksek olmalı');

        // Kombine: iki tahmin, oranlar çarpılır (5-1 = 6 gol → 8.5 altı)
        $c->call('toggleParlay', $match->id, 'winner', 'A')
            ->call('toggleParlay', $match->id, 'total_goals', 'under')
            ->set('parlayStake', 15)
            ->call('placeParlay');

        $slip = \App\Models\PredictionSlip::firstOrFail();
        $this->assertSame(2, $slip->legs()->count());
        $this->assertSame(15, $slip->stake);
        $beklenenOran = round($slip->legs->reduce(fn ($t, $l) => $t * (float) $l->odds, 1.0), 2);
        $this->assertEqualsWithDelta($beklenenOran, (float) $slip->total_odds, 0.02);

        // Bakiye: skor kuponu + kombine düşmüş, hareket kaydı tutulmuş
        $this->assertSame($baslangic - 10 - 15, $owner->refresh()->cim_balance);
        $this->assertGreaterThanOrEqual(3, \App\Models\CimTransaction::where('user_id', $owner->id)->count());

        // Nabız: kupon sekmesinde grubun tahmin dağılımı görünür
        $this->actingAs($owner)->get(route('groups.kehanet', $group))
            ->assertOk()
            ->assertSee('Grubun nabzı');

        // Kombine kuponu "Kuponlarım" sekmesinde listelenir
        Livewire::actingAs($owner)
            ->test(Groups\Kehanet::class, ['group' => $group])
            ->call('setTab', 'kuponlarim')
            ->assertSee('Kombine Kuponlarım');

        // Çim hareketleri kendi sekmesinde
        Livewire::actingAs($owner)
            ->test(Groups\Kehanet::class, ['group' => $group])
            ->call('setTab', 'cim')
            ->assertSee('Çim Hareketleri');

        // Maç sonucu: Turuncu 5-1 kazanır → kombinenin iki bacağı da tutar
        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->set('teamAScore', 5)->set('teamBScore', 1)
            ->call('saveResult');

        $slip->refresh();
        $this->assertSame('won', $slip->status);
        $this->assertSame($slip->potentialPayout(), $slip->payout);
        $this->assertSame('lost', $skorKuponu->refresh()->status, '4-2 tahmini 5-1 sonucunda kaybeder');

        // Kazanç bildirimi gider
        Notification::assertSentTo($owner, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'Kehanetin tuttu'));

        // Seri sayacı çalışır
        $seri = app(\App\Services\KehanetService::class)->streak($owner->id, $group);
        $this->assertIsInt($seri['current']);
    }

    public function test_kehanet_mac_odulleri_dagitilir_misafire_verilmez(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $golcu = $this->addMember($group);
        $misafir = $group->players()->create(['name' => 'Misafir Ali', 'positions' => []]);

        $match = $group->matches()->create([
            'created_by' => $owner->id, 'title' => 'Ödül maçı', 'starts_at' => now()->subDays(2),
            'capacity' => 14, 'status' => 'completed', 'team_a_score' => 5, 'team_b_score' => 2,
            'mvp_closes_at' => now()->subHour(),   // oylama kapandı
            'forma_goal_player_id' => $ownPlayer->id,
        ]);
        foreach ([$ownPlayer, $golcu, $misafir] as $p) {
            $match->rsvps()->create(['player_id' => $p->id, 'status' => 'going', 'team' => 'A']);
        }

        // golcü 3 gol (en çok), misafir 1 gol; MVP = golcü
        $match->goals()->create(['player_id' => $golcu->id, 'count' => 3]);
        $match->goals()->create(['player_id' => $misafir->id, 'count' => 1]);
        $match->mvpVotes()->create(['voter_id' => $owner->id, 'player_id' => $golcu->id]);

        $golcuOnce = $golcu->user->cim_balance;
        $ownerOnce = $owner->cim_balance;

        $adet = app(\App\Services\KehanetService::class)->awardMatchBonuses($match);

        // golcü: en çok gol 100 + MVP 50 + hat-trick 50 + galibiyet 15 + katılım 10 = 225
        // owner: forma 25 + galibiyet 15 + oylamaya katıldı 10 + katılım 10 = 60
        // misafir: hesapsız → hiç ödül yok
        $this->assertSame(2, $adet);
        $this->assertSame($golcuOnce + 225, $golcu->user->refresh()->cim_balance);
        $this->assertSame($ownerOnce + 60, $owner->refresh()->cim_balance);

        // Misafirin hesabı yok — ödül kaydı da oluşmaz
        $this->assertSame(0, \App\Models\CimTransaction::where('type', 'bonus')
            ->where('description', 'like', '%'.$misafir->name.'%')->count());

        // Bildirim gider
        Notification::assertSentTo($golcu->user, MatchPushNotification::class,
            fn ($n) => str_contains($n->title, 'Maç ödülün'));

        // İkinci çalıştırma tekrar ödül vermez
        $bakiye = $golcu->user->refresh()->cim_balance;
        $this->assertSame(0, app(\App\Services\KehanetService::class)->awardMatchBonuses($match->refresh()));
        $this->assertSame($bakiye, $golcu->user->refresh()->cim_balance);

        // Oylama açıkken ödül dağıtılmaz
        $acikMac = $group->matches()->create([
            'created_by' => $owner->id, 'title' => 'Yeni biten', 'starts_at' => now()->subHours(2),
            'capacity' => 14, 'status' => 'completed', 'team_a_score' => 1, 'team_b_score' => 0,
            'mvp_closes_at' => now()->addDays(5),
        ]);
        $acikMac->goals()->create(['player_id' => $golcu->id, 'count' => 1]);
        $this->assertSame(0, app(\App\Services\KehanetService::class)->awardMatchBonuses($acikMac));

        // Ödüller sekmesi: alındı/alınmadı durumu görünür
        Livewire::actingAs($golcu->user)
            ->test(Groups\Kehanet::class, ['group' => $group])
            ->call('setTab', 'oduller')
            ->assertSee('Ödüller')
            ->assertSee('En çok gol atan')
            ->assertSee('Alındı')
            ->assertSee('Alınmadı');   // henüz kazanılmamış ödüller de listelenir

        // Sadece katılım ödülü alana bildirim gitmez (spam olmasın)
        $sadeceKatilan = $this->addMember($group);
        $katilimMaci = $group->matches()->create([
            'created_by' => $owner->id, 'title' => 'Katılım maçı', 'starts_at' => now()->subDays(3),
            'capacity' => 14, 'status' => 'completed', 'team_a_score' => 1, 'team_b_score' => 1,
            'mvp_closes_at' => now()->subHour(),
        ]);
        $katilimMaci->rsvps()->create(['player_id' => $sadeceKatilan->id, 'status' => 'going', 'team' => 'A']);

        $oncekiBakiye = $sadeceKatilan->user->cim_balance;
        Notification::fake();
        app(\App\Services\KehanetService::class)->awardMatchBonuses($katilimMaci);

        $this->assertSame($oncekiBakiye + 10, $sadeceKatilan->user->refresh()->cim_balance);
        Notification::assertNothingSent();
    }

    public function test_kehanet_mac_iptalinde_cim_iade_edilir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $ownPlayer = $group->playerFor($owner);
        $friend = $this->addMember($group);

        $match = $this->makeMatch($group);
        $match->setRsvp($ownPlayer, 'going');
        $match->setRsvp($friend, 'going');

        $c = Livewire::actingAs($owner)->test(Groups\Kehanet::class, ['group' => $group]);
        $c->set("selection.{$match->id}-winner", 'A')
            ->set("stake.{$match->id}-winner", 50)
            ->call('bet', $match->id, 'winner');

        $sonra = $owner->refresh()->cim_balance;

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('cancelMatch');

        $this->assertSame('void', \App\Models\Prediction::first()->status);
        $this->assertSame($sonra + 50, $owner->refresh()->cim_balance, 'İptalde Çim iade edilmeli');
    }

    public function test_sayfalar_acilir(): void
    {
        $owner = User::factory()->create();
        $group = $this->makeGroup($owner);
        $match = $this->makeMatch($group);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Yaklaşan Maçlar');
        $this->actingAs($owner)->get(route('groups.index'))->assertOk()->assertSee('Salı Maçları');
        $this->actingAs($owner)->get(route('groups.show', $group))->assertOk()->assertSee('Oyuncu Havuzu');
        $this->actingAs($owner)->get(route('groups.rate', $group))->assertOk()->assertSee('Oyuncuları Puanla');
        $this->actingAs($owner)->get(route('groups.stats', $group))->assertOk()->assertSee('Gol Krallığı');
        $this->actingAs($owner)->get(route('matches.show', $match))->assertOk()->assertSee('Geliyor musun?');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('groups.show', $group))->assertForbidden();
        $this->actingAs($stranger)->get(route('matches.show', $match))->assertForbidden();
    }
}
