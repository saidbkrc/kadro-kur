<?php

namespace App\Services;

use App\Models\CimAward;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Çim ödül sistemi: sahadaki ve uygulama içindeki davranışların karşılığı.
 * Kupondan bağımsızdır. Her ödül cim_awards tablosuyla tek sefere kilitlenir.
 *
 * scope: match  → maç başına (MVP oylaması kapanınca dağıtılır)
 *        period → seri/dönem (5 maç serisi, aylık tam katılım)
 *        once   → hayatta bir kez
 *        repeat → tekrarlı ama nesne başına bir kez (oyuncu puanlama, rozet)
 */
class CimRewards
{
    /*
     * Tutarlar 2026-09-25'te mağaza zammına karşılık ×1,5 artırıldı. Verilmiş ödüller
     * cim_awards'ta kilitli olduğu için zam geriye dönük fark ödemez — yeni tutar
     * yalnızca bundan sonra verilenlere uygulanır. Kehanet başlığındaki etiketler de
     * buradan okur; tutarın tek kaynağı burası.
     */
    public const AWARDS = [
        'top_scorer' => ['amount' => 150, 'icon' => '⚽', 'name' => 'En çok gol atan', 'desc' => 'Maçın en golcüsü ol', 'scope' => 'match'],
        'mvp' => ['amount' => 75, 'icon' => '🏆', 'name' => 'Maçın adamı', 'desc' => 'MVP oylamasını kazan', 'scope' => 'match'],
        'hat_trick' => ['amount' => 75, 'icon' => '⚡', 'name' => 'Hat-trick', 'desc' => 'Tek maçta 3+ gol at', 'scope' => 'match'],
        'clean_sheet' => ['amount' => 75, 'icon' => '🧱', 'name' => 'Gol yemeyen kaleci', 'desc' => 'Kalede maçı gol yemeden bitir', 'scope' => 'match'],
        'perf_vote' => ['amount' => 45, 'icon' => '📊', 'name' => 'Herkese performans puanı', 'desc' => 'Skor girildikten sonraki 24 saat içinde maçtaki herkesi puanla', 'scope' => 'match'],
        'forma' => ['amount' => 40, 'icon' => '👕', 'name' => 'Forma golü', 'desc' => 'Forma golünü sen at', 'scope' => 'match'],
        'win' => ['amount' => 25, 'icon' => '🥇', 'name' => 'Galibiyet', 'desc' => 'Takımınla maçı kazan', 'scope' => 'match'],
        'attendance' => ['amount' => 15, 'icon' => '🏟️', 'name' => 'Maça katılım', 'desc' => 'Asıl kadroda sahaya çık', 'scope' => 'match'],
        // Anahtar eski ('rating_vote') — geçmiş kayıtlar bozulmasın diye korundu; artık yalnızca MVP oyu
        'rating_vote' => ['amount' => 10, 'icon' => '🗳️', 'name' => 'MVP oyu verdin', 'desc' => 'Maç sonu MVP oylamasına katıl', 'scope' => 'match'],
        'squad_vote' => ['amount' => 10, 'icon' => '✅', 'name' => 'Kadro oylaması', 'desc' => 'Kurulan kadroya oy ver', 'scope' => 'match'],
        'early_rsvp' => ['amount' => 10, 'icon' => '⏱️', 'name' => 'Erken cevap', 'desc' => 'Maçtan en az 48 saat önce katılımını bildir', 'scope' => 'match'],

        'streak_5' => ['amount' => 75, 'icon' => '🔥', 'name' => '5 maç serisi', 'desc' => 'Üst üste 5 maça çık', 'scope' => 'period'],
        'monthly_full' => ['amount' => 150, 'icon' => '📅', 'name' => 'Aylık tam katılım', 'desc' => 'Bir ayın bütün maçlarına çık', 'scope' => 'period'],

        'profile_complete' => ['amount' => 75, 'icon' => '🎯', 'name' => 'Profilini tamamla', 'desc' => 'Fotoğraf, pozisyon ve forma numarası ekle', 'scope' => 'once'],
        'rate_player' => ['amount' => 30, 'icon' => '⭐', 'name' => 'Oyuncu puanlama', 'desc' => 'Bir takım arkadaşını puanla (kişi başına bir kez)', 'scope' => 'repeat'],
        'badge_earned' => ['amount' => 40, 'icon' => '🏅', 'name' => 'Yeni rozet', 'desc' => 'Kazandığın her rozet için', 'scope' => 'repeat'],
    ];

    /**
     * "Herkese performans puanı" ödülü bu tarihten sonra oylaması kapanan maçlara
     * uygulanır. Ödül işi son 2 ayın maçlarını her saat yeniden taradığı için sınır
     * olmasa geçmiş maçlar için toplu ödeme + maç başına ayrı push giderdi.
     */
    public const PERF_VOTE_SINCE = '2026-09-25';

    /** Ödülü verir (zaten verilmişse hiçbir şey yapmaz). Verilen miktarı döndürür. */
    public function grant(int $userId, Group $group, string $key, string $ref = ''): int
    {
        $odul = self::AWARDS[$key] ?? null;

        if ($odul === null) {
            return 0;
        }

        $kayit = CimAward::firstOrCreate(
            ['user_id' => $userId, 'group_id' => $group->id, 'award_key' => $key, 'ref' => $ref],
            ['amount' => $odul['amount']],
        );

        if (! $kayit->wasRecentlyCreated) {
            return 0; // daha önce verilmiş
        }

        app(KehanetService::class)->adjustBalance($userId, $odul['amount'], 'bonus', $odul['name']);

        return $odul['amount'];
    }

    /**
     * Maç ödülleri — MVP oylaması kapandıktan sonra çalışır (o ana kadar MVP kesin değil).
     *
     * @return array<int, array{total:int, reasons:list<string>}> [user_id => özet]
     */
    public function awardForMatch(FootballMatch $match): array
    {
        if ($match->status !== 'completed' || $match->mvpOpen()) {
            return [];
        }

        $group = $match->group;
        $ref = 'match:'.$match->id;
        $rsvps = $match->mainListRsvps();

        // player_id => user_id (misafirler elenir — hesapsız Çim alamaz)
        $hesaplar = Player::whereIn('id', $rsvps->pluck('player_id'))
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id');

        /** @var array<int, list<string>> $kazananlar [player_id => ödül anahtarları] */
        $kazananlar = [];
        $ekle = function (?int $playerId, string $key) use (&$kazananlar, $hesaplar) {
            if ($playerId !== null && $hesaplar->has($playerId)) {
                $kazananlar[$playerId][] = $key;
            }
        };

        $beraberlik = $match->team_a_score === $match->team_b_score;
        $kazanan = $match->team_a_score > $match->team_b_score ? 'A' : 'B';

        foreach ($rsvps as $rsvp) {
            $ekle($rsvp->player_id, 'attendance');

            if (! $beraberlik && $rsvp->team === $kazanan) {
                $ekle($rsvp->player_id, 'win');
            }

            // Gol yemeyen kaleci
            $yenen = $rsvp->team === 'A' ? $match->team_b_score : $match->team_a_score;
            if ($rsvp->team !== null && $yenen === 0 && $rsvp->player?->isGoalkeeper()) {
                $ekle($rsvp->player_id, 'clean_sheet');
            }

            // Erken cevap: maçtan en az 48 saat önce "geliyorum" demiş
            if ($rsvp->created_at && $rsvp->created_at->lte($match->starts_at->copy()->subHours(48))) {
                $ekle($rsvp->player_id, 'early_rsvp');
            }
        }

        // Goller: en çok atan(lar) + hat-trick
        $goller = $match->goals()->get();
        if ($goller->isNotEmpty()) {
            $enFazla = $goller->max('count');
            foreach ($goller as $gol) {
                if ($gol->count >= 3) {
                    $ekle($gol->player_id, 'hat_trick');
                }
                if ($gol->count === $enFazla) {
                    $ekle($gol->player_id, 'top_scorer');
                }
            }
        }

        // MVP
        $mvpOylari = $match->mvpVotes()->selectRaw('player_id, count(*) as oy')->groupBy('player_id')->get();
        if ($mvpOylari->isNotEmpty()) {
            $enCok = $mvpOylari->max('oy');
            foreach ($mvpOylari->where('oy', $enCok) as $oy) {
                $ekle($oy->player_id, 'mvp');
            }
        }

        if ($match->forma_goal_player_id) {
            $ekle($match->forma_goal_player_id, 'forma');
        }

        // Katılım ödülleri kullanıcı bazlı: kadro oylaması, MVP oyu, eksiksiz performans puanlaması
        $oyVerenler = $match->squadVotes()->pluck('user_id');
        $mvpOyVerenler = $match->mvpVotes()->pluck('voter_id');
        $tamPuanlayanlar = $this->fullPerformanceRaters($match, $rsvps);

        $ozet = [];

        foreach ($kazananlar as $playerId => $anahtarlar) {
            $userId = $hesaplar[$playerId];

            if ($oyVerenler->contains($userId)) {
                $anahtarlar[] = 'squad_vote';
            }
            if ($mvpOyVerenler->contains($userId)) {
                $anahtarlar[] = 'rating_vote';
            }
            if ($tamPuanlayanlar->contains($userId)) {
                $anahtarlar[] = 'perf_vote';
            }

            $toplam = 0;
            $sebepler = [];

            foreach (array_unique($anahtarlar) as $key) {
                $verilen = $this->grant($userId, $group, $key, $ref);

                if ($verilen > 0) {
                    $toplam += $verilen;
                    $sebepler[] = self::AWARDS[$key]['icon'].' '.self::AWARDS[$key]['name'];
                }
            }

            if ($toplam > 0) {
                $ozet[$userId] = ['total' => $toplam, 'reasons' => $sebepler];
            }
        }

        // Seri ve aylık katılım ödülleri
        foreach ($hesaplar as $playerId => $userId) {
            $ek = $this->awardPeriodic($userId, $playerId, $group, $match);

            if ($ek['total'] > 0) {
                $ozet[$userId] = [
                    'total' => ($ozet[$userId]['total'] ?? 0) + $ek['total'],
                    'reasons' => array_merge($ozet[$userId]['reasons'] ?? [], $ek['reasons']),
                ];
            }
        }

        $match->update(['bonus_awarded_at' => now()]);

        return $ozet;
    }

    /**
     * Maçtaki puanlanabilir herkesi (asıl kadro; kendisi ve misafirler hariç) oylama
     * penceresi içinde puanlamış kullanıcılar. Pencere dışındaki puanlar sayılmaz —
     * kupon da o ana kadarki puanlarla sonuçlanıyor, ödül zamanında puanı teşvik eder.
     */
    protected function fullPerformanceRaters(FootballMatch $match, $rsvps): \Illuminate\Support\Collection
    {
        if ($match->mvp_closes_at === null || $match->mvp_closes_at->lt(\Illuminate\Support\Carbon::parse(self::PERF_VOTE_SINCE))) {
            return collect();
        }

        // Puanlanabilir oyuncular: hesabı olan (misafir değil) asıl kadro
        $puanlanabilir = $rsvps->filter(fn ($r) => $r->player && ! $r->player->isGuest())
            ->mapWithKeys(fn ($r) => [$r->player_id => $r->player->user_id]);

        return $match->performanceRatings()
            ->where('created_at', '<=', $match->mvp_closes_at)
            ->get(['rater_id', 'player_id'])
            ->groupBy('rater_id')
            ->filter(function ($puanlar, $raterId) use ($puanlanabilir) {
                // Kendisi hariç herkes: puanlanabilir kümede kendisi varsa o düşülür
                $hedef = $puanlanabilir->reject(fn ($userId) => (int) $userId === (int) $raterId)->keys();

                return $hedef->isNotEmpty() && $hedef->diff($puanlar->pluck('player_id'))->isEmpty();
            })
            ->keys()
            ->map(fn ($id) => (int) $id);
    }

    /** 5 maç serisi ve aylık tam katılım. */
    protected function awardPeriodic(int $userId, int $playerId, Group $group, FootballMatch $match): array
    {
        $toplam = 0;
        $sebepler = [];

        // Grubun tamamlanmış maçları (kronolojik) ve oyuncunun katıldıkları
        $maclar = $group->matches()->where('status', 'completed')
            ->where('starts_at', '<=', $match->starts_at)
            ->orderBy('starts_at')->get(['id', 'starts_at']);

        $katildigi = DB::table('rsvps')
            ->whereIn('match_id', $maclar->pluck('id'))
            ->where('player_id', $playerId)
            ->where('status', 'going')->whereNull('waitlist_position')
            ->pluck('match_id')->flip();

        // Üst üste seri — her 5'in katında ödül
        $seri = 0;
        foreach ($maclar as $m) {
            $seri = $katildigi->has($m->id) ? $seri + 1 : 0;
        }

        if ($seri > 0 && $seri % 5 === 0) {
            $verilen = $this->grant($userId, $group, 'streak_5', 'match:'.$match->id);
            if ($verilen > 0) {
                $toplam += $verilen;
                $sebepler[] = self::AWARDS['streak_5']['icon'].' '.self::AWARDS['streak_5']['name'];
            }
        }

        // Aylık tam katılım: o ayın tüm maçlarına çıkmış (en az 2 maç)
        $ay = $match->starts_at->format('Y-m');
        $aydakiler = $maclar->filter(fn ($m) => \Illuminate\Support\Carbon::parse($m->starts_at)->format('Y-m') === $ay);

        if ($aydakiler->count() >= 2 && $aydakiler->every(fn ($m) => $katildigi->has($m->id))) {
            $verilen = $this->grant($userId, $group, 'monthly_full', 'month:'.$ay);
            if ($verilen > 0) {
                $toplam += $verilen;
                $sebepler[] = self::AWARDS['monthly_full']['icon'].' '.self::AWARDS['monthly_full']['name'];
            }
        }

        return ['total' => $toplam, 'reasons' => $sebepler];
    }

    /**
     * Maçtan bağımsız ödüller: profil tamamlama, oyuncu puanlama, kazanılan rozetler.
     * Sayfa açıldığında tembel olarak çalışır.
     */
    public function syncStandingAwards(User $user, Group $group): int
    {
        $player = $group->playerFor($user);

        if ($player === null) {
            return 0;
        }

        $toplam = 0;

        // Profil tamamlama
        if ($player->photo_path && ! empty($player->positions) && $player->shirt_number) {
            $toplam += $this->grant($user->id, $group, 'profile_complete');
        }

        // Puanladığı her oyuncu için (grup içi)
        $puanladiklari = DB::table('attribute_ratings')
            ->where('rater_id', $user->id)
            ->whereIn('player_id', $group->players()->pluck('id'))
            ->pluck('player_id');

        foreach ($puanladiklari as $pid) {
            $toplam += $this->grant($user->id, $group, 'rate_player', 'player:'.$pid);
        }

        // Kazandığı her rozet için
        foreach ($player->badges()->pluck('badge_key') as $rozet) {
            $toplam += $this->grant($user->id, $group, 'badge_earned', 'badge:'.$rozet);
        }

        return $toplam;
    }

    /**
     * Ödül sayfası için durum tablosu.
     *
     * @return array<string, array{count:int, total:int}>
     */
    public function statusFor(User $user, Group $group): array
    {
        $kazanilan = CimAward::where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->selectRaw('award_key, count(*) as adet, sum(amount) as toplam')
            ->groupBy('award_key')
            ->get()
            ->keyBy('award_key');

        $sonuc = [];

        foreach (self::AWARDS as $key => $odul) {
            $satir = $kazanilan->get($key);
            $sonuc[$key] = [
                'count' => (int) ($satir->adet ?? 0),
                'total' => (int) ($satir->toplam ?? 0),
            ];
        }

        return $sonuc;
    }
}
