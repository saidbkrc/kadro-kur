<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Player;
use App\Models\SeasonVote;
use App\Models\User;
use App\Support\Season;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sezon sonu oylaması: oyuncular sezonu kendi aralarında oylar. İstatistiğin zaten
 * ölçtükleri (gol, MVP, katılım) burada yok — yalnızca sayıya dökülemeyenler.
 *
 * Akış: sezon biter → VOTING_DAYS gün oylama → kapanınca kazananlar belli olur,
 * Çim dağıtılır ve profilde kalıcı unvan olarak görünür (sonuçlar oylardan türetilir).
 */
class SeasonVoting
{
    public const CATEGORIES = [
        'best' => ['icon' => '⭐', 'name' => 'Sezonun En İyi Oyuncusu', 'hint' => 'Bu sezon sahada en çok fark yaratan'],
        'team' => ['icon' => '🤝', 'name' => 'Takım Oyuncusu', 'hint' => 'Pas veren, boşa koşan, bencil olmayan'],
        'improved' => ['icon' => '📈', 'name' => 'En Çok Gelişen', 'hint' => 'Sezon başına göre en çok ilerleyen'],
        'fairplay' => ['icon' => '🕊️', 'name' => 'Centilmen', 'hint' => 'Tartışmayan, sert girmeyen, kaybedince de keyifli olan'],
    ];

    /** Aday olmak için sezonda çıkılması gereken en az maç. */
    public const MIN_MATCHES_CANDIDATE = 3;

    /** Oy vermek için sezonda en az bu kadar maça çıkmış olmak gerekir. */
    public const MIN_MATCHES_VOTER = 1;

    /** Hesabı olan oyuncuların sezondaki (asıl listede) maç sayıları: [player_id => adet]. */
    public function playedCounts(Group $group, Season $season): Collection
    {
        $macIdler = $group->matches()->where('status', 'completed')
            ->whereBetween('starts_at', [$season->start, $season->end()])
            ->pluck('id');

        return DB::table('rsvps')
            ->join('players', 'players.id', '=', 'rsvps.player_id')
            ->whereIn('rsvps.match_id', $macIdler)
            ->where('rsvps.status', 'going')->whereNull('rsvps.waitlist_position')
            ->where('players.group_id', $group->id)
            ->whereNotNull('players.user_id')
            ->selectRaw('players.id, count(*) as c')
            ->groupBy('players.id')
            ->pluck('c', 'id')
            ->map(fn ($c) => (int) $c);
    }

    /** Oy verilebilecek oyuncular (hesaplı, en az MIN_MATCHES_CANDIDATE maç), isme göre. */
    public function candidates(Group $group, Season $season): Collection
    {
        $uygun = $this->playedCounts($group, $season)
            ->filter(fn ($c) => $c >= self::MIN_MATCHES_CANDIDATE)->keys();

        return $group->players()->whereIn('id', $uygun)->orderBy('name')->get();
    }

    /** Kullanıcı bu sezon için oy verebilir mi? (sezonda en az MIN_MATCHES_VOTER maç) */
    public function canVote(User $user, Group $group, Season $season): bool
    {
        $oyuncu = $group->playerFor($user);

        return $oyuncu !== null
            && ($this->playedCounts($group, $season)[$oyuncu->id] ?? 0) >= self::MIN_MATCHES_VOTER;
    }

    /**
     * Oy verir / değiştirir.
     *
     * @return array{ok: bool, message: string}
     */
    public function vote(User $user, Group $group, Season $season, string $category, int $playerId): array
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            return ['ok' => false, 'message' => 'Geçersiz kategori.'];
        }

        if (! $season->isVotingOpen()) {
            return ['ok' => false, 'message' => 'Bu sezonun oylaması açık değil.'];
        }

        if (! $this->canVote($user, $group, $season)) {
            return ['ok' => false, 'message' => 'Bu sezonda maça çıkmadığın için oy veremezsin.'];
        }

        // İzolasyon: aday yalnızca bu grubun, bu sezonun aday listesinden
        $aday = $this->candidates($group, $season)->firstWhere('id', $playerId);

        if ($aday === null) {
            return ['ok' => false, 'message' => 'Bu oyuncu bu sezon aday değil.'];
        }

        if ($aday->user_id === $user->id) {
            return ['ok' => false, 'message' => 'Kendine oy veremezsin 🙂'];
        }

        SeasonVote::updateOrCreate(
            ['group_id' => $group->id, 'season' => $season->key(), 'category' => $category, 'voter_id' => $user->id],
            ['player_id' => $aday->id],
        );

        return ['ok' => true, 'message' => '✓ Oyun kaydedildi.'];
    }

    /** Kullanıcının verdiği oylar: [kategori => player_id]. */
    public function myVotes(User $user, Group $group, Season $season): array
    {
        return SeasonVote::where('group_id', $group->id)->where('season', $season->key())
            ->where('voter_id', $user->id)
            ->pluck('player_id', 'category')->all();
    }

    /** Kaç kişi oy verdi (en az bir kategoride). */
    public function voterCount(Group $group, Season $season): int
    {
        return SeasonVote::where('group_id', $group->id)->where('season', $season->key())
            ->distinct()->count('voter_id');
    }

    /**
     * Sonuçlar — yalnızca oylama kapandıktan sonra (açıkken kimse ara sonuç görmez).
     * Eşitlikte zirvedeki herkes kazanır.
     *
     * @return array<string, array{winners: Collection<int, Player>, votes: int}> [kategori => …]
     */
    public function results(Group $group, Season $season): array
    {
        if (! $season->isVotingClosed()) {
            return [];
        }

        $sayim = SeasonVote::where('group_id', $group->id)->where('season', $season->key())
            ->selectRaw('category, player_id, count(*) as c')
            ->groupBy('category', 'player_id')
            ->get()
            ->groupBy('category');

        $oyuncular = $group->players()->whereIn('id', $sayim->flatten()->pluck('player_id'))->get()->keyBy('id');

        $sonuc = [];

        foreach (self::CATEGORIES as $kat => $_) {
            $satirlar = $sayim->get($kat);

            if ($satirlar === null || $satirlar->isEmpty()) {
                continue;
            }

            $zirve = (int) $satirlar->max('c');

            $sonuc[$kat] = [
                'votes' => $zirve,
                'winners' => $satirlar->where('c', $zirve)
                    ->map(fn ($r) => $oyuncular->get($r->player_id))
                    ->filter()->values(),
            ];
        }

        return $sonuc;
    }

    /**
     * Oyuncunun kazandığı sezon unvanları (tüm kapanmış sezonlar), yeniden eskiye.
     *
     * @return list<array{season: Season, category: string}>
     */
    public function titlesFor(Player $player): array
    {
        $unvanlar = [];

        foreach (Season::pastForGroup($player->group) as $sezon) {
            foreach ($this->results($player->group, $sezon) as $kat => $r) {
                if ($r['winners']->contains('id', $player->id)) {
                    $unvanlar[] = ['season' => $sezon, 'category' => $kat];
                }
            }
        }

        return $unvanlar;
    }

    /**
     * Saatlik iş: oylama açılınca / kapanmadan 1 gün önce bildirim, kapanınca ödül.
     * Sezon sistemi açılmadan önce biten sezonlar için hiçbir şey yapılmaz.
     */
    public function runDue(): void
    {
        $sezon = Season::current()->previous();

        if ($sezon->key() < Season::LAUNCH_KEY) {
            return;
        }

        foreach (Group::all() as $group) {
            try {
                if ($sezon->isVotingOpen()) {
                    $this->notifyOpen($group, $sezon);
                    $this->notifyLastDay($group, $sezon);
                } elseif ($sezon->isVotingClosed()) {
                    $this->award($group, $sezon);
                }
            } catch (\Throwable $e) {
                report($e); // bir grubun sorunu diğerlerini engellemesin
            }
        }
    }

    /** Kapanan oylamanın ödülleri: kazanana kategori başına, tüm kategorilerde oy verene katılım. Tek seferlik. */
    public function award(Group $group, Season $season): void
    {
        $cim = app(CimRewards::class);

        foreach ($this->results($group, $season) as $kat => $r) {
            foreach ($r['winners'] as $oyuncu) {
                if ($oyuncu->user_id === null) {
                    continue;
                }

                $verilen = $cim->grant($oyuncu->user_id, $group, 'season_vote_win', 'seasonvote:'.$season->key().':'.$kat);

                if ($verilen > 0 && ($u = $oyuncu->user)) {
                    app(PushNotifier::class)->seasonVoteWon($u, $group, $season, self::CATEGORIES[$kat], $verilen);
                }
            }
        }

        // Dört kategoride de oy verenlere katılım ödülü
        $tamOyVerenler = SeasonVote::where('group_id', $group->id)->where('season', $season->key())
            ->selectRaw('voter_id, count(distinct category) as k')
            ->groupBy('voter_id')
            ->get()
            ->filter(fn ($r) => (int) $r->k >= count(self::CATEGORIES))
            ->pluck('voter_id');

        foreach ($tamOyVerenler as $userId) {
            $cim->grant((int) $userId, $group, 'season_vote_cast', 'seasonvote:'.$season->key());
        }
    }

    /** Oylama açıldı bildirimi — oy verebilecek herkese, grup+sezon başına bir kez. */
    protected function notifyOpen(Group $group, Season $season): void
    {
        if (! Cache::add('sezon-oylama-acildi:'.$group->id.':'.$season->key(), 1, now()->addDays(30))) {
            return;
        }

        app(PushNotifier::class)->seasonVoteOpened($this->eligibleUsers($group, $season), $group, $season);
    }

    /** Son gün hatırlatması — henüz tüm kategorilerde oy vermemişlere, bir kez. */
    protected function notifyLastDay(Group $group, Season $season): void
    {
        if (now()->lt($season->votingClosesAt()->copy()->subDay())) {
            return;
        }

        if (! Cache::add('sezon-oylama-son-gun:'.$group->id.':'.$season->key(), 1, now()->addDays(30))) {
            return;
        }

        $tamamlayanlar = SeasonVote::where('group_id', $group->id)->where('season', $season->key())
            ->selectRaw('voter_id, count(distinct category) as k')->groupBy('voter_id')->get()
            ->filter(fn ($r) => (int) $r->k >= count(self::CATEGORIES))->pluck('voter_id');

        app(PushNotifier::class)->seasonVoteLastDay(
            $this->eligibleUsers($group, $season)->reject(fn (User $u) => $tamamlayanlar->contains($u->id)),
            $group, $season,
        );
    }

    /** Oy verebilecek kullanıcılar. */
    protected function eligibleUsers(Group $group, Season $season): Collection
    {
        $oyuncuIdler = $this->playedCounts($group, $season)
            ->filter(fn ($c) => $c >= self::MIN_MATCHES_VOTER)->keys();

        return User::whereIn('id', $group->players()->whereIn('id', $oyuncuIdler)->pluck('user_id'))->get();
    }
}
