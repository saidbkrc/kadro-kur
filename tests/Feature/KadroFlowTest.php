<?php

namespace Tests\Feature;

use App\Livewire\Groups;
use App\Livewire\Matches;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchScheduler;
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

    public function test_sonuc_girilir_mvp_24_saat_acilir_oy_degistirilemez(): void
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

        // 24 saat geçince oylama kapanır
        $this->travel(25)->hours();
        $this->assertFalse($match->refresh()->mvpOpen());

        Livewire::actingAs($owner)
            ->test(Matches\Show::class, ['match' => $match])
            ->call('voteMvp', $friend->id)
            ->assertStatus(403);
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
