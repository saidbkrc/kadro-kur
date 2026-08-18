<?php

namespace App\Services;

use App\Models\CimTransaction;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\MatchEvent;
use App\Models\Prediction;
use App\Models\PredictionSlip;
use App\Models\User;
use App\Support\Kehanet;
use Illuminate\Support\Facades\DB;

/**
 * Kehanet: kupon yapma, haftalık Çim yüklemesi ve sonuçlandırma.
 * Çim sanaldır — gerçek parayla ilişkisi yoktur.
 */
class KehanetService
{
    /**
     * Bakiyeyi değiştirir ve hareket kaydı düşer — tüm Çim hareketleri buradan geçer.
     * Kilitleyerek okur, eşzamanlı kupon/ödemede tutarsızlık olmaz.
     */
    public function adjustBalance(int $userId, int $amount, string $type, ?string $description = null): int
    {
        return DB::transaction(function () use ($userId, $amount, $type, $description) {
            $user = User::lockForUpdate()->findOrFail($userId);
            $yeni = $user->cim_balance + $amount;

            $user->forceFill(['cim_balance' => $yeni])->save();

            CimTransaction::create([
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'balance_after' => $yeni,
            ]);

            return $yeni;
        });
    }

    /** Haftalık yükleme: bu hafta verilmediyse ekler. İlk kez giren başlangıç bakiyesi alır. */
    public function grantWeeklyIfDue(User $user): int
    {
        $ilkKez = $user->cim_granted_at === null;

        if (! $ilkKez && $user->cim_granted_at->gte(now()->startOfWeek())) {
            return 0;
        }

        $miktar = $ilkKez ? Kehanet::STARTING_BALANCE : Kehanet::WEEKLY_GRANT;

        $this->adjustBalance($user->id, $miktar, 'grant', $ilkKez ? 'Başlangıç bakiyesi' : 'Haftalık Çim');
        $user->forceFill(['cim_granted_at' => now()])->save();
        $user->refresh();

        return $miktar;
    }

    /** Maça kupon yapılabilir mi? (oynanmamış ve başlama saati geçmemiş) */
    public function bettingOpen(FootballMatch $match): bool
    {
        return $match->status === 'scheduled' && $match->starts_at->isFuture();
    }

    /**
     * Kupon yapar/günceller. Aynı market'te önceki kupon varsa iade edilip yenisi yazılır.
     *
     * @return array{ok: bool, message: string}
     */
    public function placeBet(User $user, FootballMatch $match, string $market, string $selection, int $stake): array
    {
        if (! array_key_exists($market, Kehanet::MARKETS)) {
            return ['ok' => false, 'message' => 'Geçersiz tahmin türü.'];
        }

        if (! $this->bettingOpen($match)) {
            return ['ok' => false, 'message' => 'Bu maç için kupon kapandı.'];
        }

        if ($stake < Kehanet::MIN_STAKE || $stake > Kehanet::MAX_STAKE) {
            return ['ok' => false, 'message' => 'Tutar '.Kehanet::MIN_STAKE.'-'.Kehanet::MAX_STAKE.' Çim arasında olmalı.'];
        }

        // Kendi hakkında tahmin yapılamaz (takım market'leri serbest)
        if ($hata = $this->selfBetError($user, $match, $market, $selection)) {
            return ['ok' => false, 'message' => $hata];
        }

        // Kupon kesindir: aynı maç + market'te ikinci kupon yapılamaz, değiştirilemez
        $zatenVar = Prediction::where('user_id', $user->id)
            ->where('match_id', $match->id)
            ->where('market_key', $market)
            ->whereNull('slip_id')
            ->where('status', 'pending')
            ->exists();

        if ($zatenVar) {
            return ['ok' => false, 'message' => 'Bu tahmin için kuponun zaten var — kuponlar değiştirilemez.'];
        }

        if ($user->cim_balance < $stake) {
            return ['ok' => false, 'message' => 'Yeterli Çim yok.'];
        }

        $odds = app(OddsCalculator::class)->odds($match, $market, $selection);

        Prediction::create([
            'user_id' => $user->id,
            'match_id' => $match->id,
            'market_key' => $market,
            'selection' => $selection,
            'odds' => $odds,
            'stake' => $stake,
        ]);

        $this->adjustBalance($user->id, -$stake, 'bet', Kehanet::label($market));

        return ['ok' => true, 'message' => "Kupon yapıldı — oran {$odds}×"];
    }

    /**
     * Kombine kupon: birden çok tahmin tek kuponda, oranlar çarpılır, hepsi tutmalı.
     *
     * @param  list<array{match_id:int, market:string, selection:string}>  $legs
     */
    public function placeParlay(User $user, Group $group, array $legs, int $stake): array
    {
        if (count($legs) < Kehanet::MIN_LEGS || count($legs) > Kehanet::MAX_LEGS) {
            return ['ok' => false, 'message' => 'Kombine '.Kehanet::MIN_LEGS.'-'.Kehanet::MAX_LEGS.' tahmin içermeli.'];
        }

        if ($stake < Kehanet::MIN_STAKE || $stake > Kehanet::MAX_STAKE) {
            return ['ok' => false, 'message' => 'Tutar '.Kehanet::MIN_STAKE.'-'.Kehanet::MAX_STAKE.' Çim arasında olmalı.'];
        }

        if ($user->cim_balance < $stake) {
            return ['ok' => false, 'message' => 'Yeterli Çim yok.'];
        }

        $odds = app(OddsCalculator::class);
        $hazir = [];
        $toplamOran = 1.0;

        foreach ($legs as $leg) {
            $match = $group->matches()->find($leg['match_id']);

            if (! $match || ! $this->bettingOpen($match) || ! array_key_exists($leg['market'], Kehanet::MARKETS)) {
                return ['ok' => false, 'message' => 'Kombinedeki bir tahmin geçersiz.'];
            }

            if ($hata = $this->selfBetError($user, $match, $leg['market'], $leg['selection'])) {
                return ['ok' => false, 'message' => $hata];
            }

            $o = $odds->odds($match, $leg['market'], $leg['selection']);
            $toplamOran *= $o;

            $hazir[] = ['match' => $match, 'market' => $leg['market'], 'selection' => $leg['selection'], 'odds' => $o];
        }

        $toplamOran = round(min(500, $toplamOran), 2);

        DB::transaction(function () use ($user, $group, $hazir, $stake, $toplamOran) {
            $slip = PredictionSlip::create([
                'user_id' => $user->id,
                'group_id' => $group->id,
                'stake' => $stake,
                'total_odds' => $toplamOran,
            ]);

            foreach ($hazir as $leg) {
                Prediction::create([
                    'user_id' => $user->id,
                    'match_id' => $leg['match']->id,
                    'slip_id' => $slip->id,
                    'market_key' => $leg['market'],
                    'selection' => $leg['selection'],
                    'odds' => $leg['odds'],
                    'stake' => 0, // tutar kuponun kendisinde
                ]);
            }
        });

        $this->adjustBalance($user->id, -$stake, 'bet', count($hazir).'\'li kombine');

        return ['ok' => true, 'message' => "Kombine yapıldı — toplam oran {$toplamOran}×"];
    }

    /** Kendi hakkında tahmin kontrolü; sorun varsa mesaj döner. */
    protected function selfBetError(User $user, FootballMatch $match, string $market, string $selection): ?string
    {
        if ((Kehanet::MARKETS[$market]['kind'] ?? '') !== 'oyuncu') {
            return null;
        }

        $kendi = $match->group->playerFor($user);

        return ($kendi && (int) $selection === $kendi->id)
            ? 'Kendinle ilgili tahmin yapamazsın 🙂'
            : null;
    }

    /**
     * Kadro değişti: artık asıl listede olmayan oyuncular üzerine yapılmış
     * bekleyen kuponlar geçersiz sayılır ve Çim iade edilir.
     * (Takım market'leri etkilenmez — Turuncu/Yeşil yerinde duruyor.)
     */
    public function voidBetsForMissingPlayers(FootballMatch $match): int
    {
        $kadro = $match->mainListRsvps()->pluck('player_id')->map(fn ($id) => (string) $id);

        $oyuncuMarketleri = collect(Kehanet::MARKETS)
            ->filter(fn ($m) => $m['kind'] === 'oyuncu')
            ->keys();

        $etkilenen = Prediction::where('match_id', $match->id)
            ->where('status', 'pending')
            ->whereIn('market_key', $oyuncuMarketleri)
            ->get()
            ->reject(fn (Prediction $p) => $kadro->contains($p->selection));

        foreach ($etkilenen as $kupon) {
            $kupon->update(['status' => 'void', 'payout' => $kupon->stake, 'settled_at' => now()]);

            if ($kupon->slip_id === null) {
                $this->adjustBalance($kupon->user_id, $kupon->stake, 'refund', 'Kadro değişti');
            } else {
                $this->voidSlip($kupon->slip, 'Kadro değişti');
            }
        }

        return $etkilenen->count();
    }

    /**
     * Maçın bekleyen kuponlarını sonuçlandırır.
     * Sonucu henüz belli olmayan market'ler (başkan işaretlemediyse) beklemede kalır.
     */
    public function settleMatch(FootballMatch $match): int
    {
        if ($match->status === 'cancelled') {
            return $this->voidMatch($match, 'Maç iptal edildi');
        }

        if ($match->status !== 'completed') {
            return 0;
        }

        $sayac = 0;
        $kazananlar = []; // [user_id => toplam net kazanç] → bildirim için

        foreach (Prediction::where('match_id', $match->id)->where('status', 'pending')->get() as $kupon) {
            $tuttu = $this->evaluate($match, $kupon->market_key, $kupon->selection);

            if ($tuttu === null) {
                continue; // sonuç henüz belli değil (başkan işaretlemedi) — beklemede kalır
            }

            // Kombine bacağı: ödeme kuponun kendisinde yapılır
            if ($kupon->slip_id !== null) {
                $kupon->update(['status' => $tuttu ? 'won' : 'lost', 'settled_at' => now()]);
                $sayac++;

                continue;
            }

            $odeme = $tuttu ? (int) round($kupon->stake * (float) $kupon->odds) : 0;

            $kupon->update([
                'status' => $tuttu ? 'won' : 'lost',
                'payout' => $odeme,
                'settled_at' => now(),
            ]);

            if ($odeme > 0) {
                $this->adjustBalance($kupon->user_id, $odeme, 'win', Kehanet::label($kupon->market_key));
                $kazananlar[$kupon->user_id] = ($kazananlar[$kupon->user_id] ?? 0) + ($odeme - $kupon->stake);
            }

            $sayac++;
        }

        // Bacakları tamamlanan kombineleri kapat
        foreach ($this->settleSlips($match) as $userId => $net) {
            $kazananlar[$userId] = ($kazananlar[$userId] ?? 0) + $net;
        }

        // Kazananlara tek bildirim
        foreach ($kazananlar as $userId => $net) {
            if ($net > 0 && ($u = User::find($userId))) {
                app(PushNotifier::class)->kehanetWin($u, $match, $net);
            }
        }

        // Oylama zaten kapandıysa maç ödüllerini de dağıt
        $this->awardMatchBonuses($match->refresh());

        return $sayac;
    }

    /**
     * Bacaklarının tamamı sonuçlanmış kombineleri kapatır.
     * Bir bacak bile kaybederse kupon anında kaybeder (diğerlerini beklemez).
     *
     * @return array<int, int> [user_id => net kazanç]
     */
    protected function settleSlips(FootballMatch $match): array
    {
        $slipIdler = Prediction::where('match_id', $match->id)->whereNotNull('slip_id')->pluck('slip_id')->unique();
        $kazananlar = [];

        foreach (PredictionSlip::whereIn('id', $slipIdler)->where('status', 'pending')->with('legs')->get() as $slip) {
            $bacaklar = $slip->legs;

            if ($bacaklar->contains(fn (Prediction $p) => $p->status === 'lost')) {
                $slip->update(['status' => 'lost', 'settled_at' => now()]);

                continue;
            }

            if ($bacaklar->contains(fn (Prediction $p) => $p->status === 'pending')) {
                continue; // hâlâ bekleyen bacak var
            }

            // Hepsi kazandı
            $odeme = $slip->potentialPayout();
            $slip->update(['status' => 'won', 'payout' => $odeme, 'settled_at' => now()]);
            $this->adjustBalance($slip->user_id, $odeme, 'win', $bacaklar->count()."'li kombine");
            $kazananlar[$slip->user_id] = ($kazananlar[$slip->user_id] ?? 0) + ($odeme - $slip->stake);
        }

        return $kazananlar;
    }

    /** İptal edilen maçta tüm kuponları geçersiz sayar, tutarları iade eder. */
    public function voidMatch(FootballMatch $match, string $sebep = 'Maç iptal edildi'): int
    {
        $sayac = 0;

        foreach (Prediction::where('match_id', $match->id)->where('status', 'pending')->get() as $kupon) {
            $kupon->update(['status' => 'void', 'payout' => $kupon->stake, 'settled_at' => now()]);

            if ($kupon->slip_id === null) {
                $this->adjustBalance($kupon->user_id, $kupon->stake, 'refund', $sebep);
            } else {
                $this->voidSlip($kupon->slip, $sebep);
            }

            $sayac++;
        }

        return $sayac;
    }

    /** Kombine kuponu geçersiz sayar ve tutarı bir kez iade eder. */
    protected function voidSlip(?PredictionSlip $slip, string $sebep): void
    {
        if ($slip === null || $slip->status !== 'pending') {
            return;
        }

        $slip->update(['status' => 'void', 'payout' => $slip->stake, 'settled_at' => now()]);
        $this->adjustBalance($slip->user_id, $slip->stake, 'refund', $sebep.' (kombine)');
    }

    /* ---------- maç başarı ödülleri ---------- */

    /**
     * Maç ödüllerini dağıtır (CimRewards'a devreder) ve kazananlara bildirim gönderir.
     * Sadece katılım ödülü alanlara push gitmez — herkese bildirim spam olurdu.
     */
    public function awardMatchBonuses(FootballMatch $match): int
    {
        $ozet = app(CimRewards::class)->awardForMatch($match);

        $esik = CimRewards::AWARDS['attendance']['amount'];

        foreach ($ozet as $userId => $bilgi) {
            if ($bilgi['total'] > $esik && ($u = User::find($userId))) {
                app(PushNotifier::class)->kehanetBonus($u, $match, $bilgi['total'], $bilgi['reasons']);
            }
        }

        return count($ozet);
    }

    /** Oylaması kapanmış maçların ödüllerini dağıtır (scheduler saatlik çağırır). */
    public function awardDueBonuses(): int
    {
        $toplam = 0;

        $adaylar = FootballMatch::where('status', 'completed')
            ->where('starts_at', '>=', now()->subMonths(2)) // çok eskiye geriye dönük dağıtma
            ->get();

        foreach ($adaylar as $match) {
            $toplam += $this->awardMatchBonuses($match);
        }

        return $toplam;
    }

    /* ---------- istatistikler ---------- */

    /** Kullanıcının güncel tutturma serisi ve en uzun serisi. */
    public function streak(int $userId, Group $group): array
    {
        $sonuclar = Prediction::where('user_id', $userId)
            ->whereNull('slip_id')
            ->whereIn('match_id', $group->matches()->pluck('id'))
            ->whereIn('status', ['won', 'lost'])
            ->orderBy('settled_at')
            ->pluck('status');

        $guncel = 0;
        $enUzun = 0;

        foreach ($sonuclar as $s) {
            $guncel = $s === 'won' ? $guncel + 1 : 0;
            $enUzun = max($enUzun, $guncel);
        }

        return ['current' => $guncel, 'best' => $enUzun];
    }

    /**
     * Grup tahmin nabzı: bir maçtaki bekleyen kuponların market bazında dağılımı.
     *
     * @return array<string, array<string, int>> [market => [seçim => kişi sayısı]]
     */
    public function pulse(FootballMatch $match): array
    {
        return Prediction::where('match_id', $match->id)
            ->where('status', 'pending')
            ->get()
            ->groupBy('market_key')
            ->map(fn ($rows) => $rows->countBy('selection')->all())
            ->all();
    }

    /**
     * Geçmiş ayların "Ayın Kâhini" birincileri (bu ay hariç).
     *
     * @return array<int, list<string>> [user_id => ['2026-07', ...]]
     */
    public function pastMonthlyChampions(Group $group): array
    {
        // Ay gruplaması PHP tarafında — sürücüden bağımsız (MySQL/SQLite aynı çalışır)
        $satirlar = Prediction::whereIn('match_id', $group->matches()->pluck('id'))
            ->whereIn('status', ['won', 'lost'])
            ->whereNotNull('settled_at')
            ->where('settled_at', '<', now()->startOfMonth())
            ->get(['user_id', 'payout', 'stake', 'settled_at']);

        $sampiyonlar = [];

        foreach ($satirlar->groupBy(fn ($p) => $p->settled_at->format('Y-m')) as $ay => $rows) {
            $netler = $rows->groupBy('user_id')
                ->map(fn ($k) => $k->sum('payout') - $k->sum('stake'))
                ->filter(fn ($net) => $net > 0)
                ->sortDesc();

            if ($netler->isNotEmpty()) {
                $sampiyonlar[$netler->keys()->first()][] = $ay;
            }
        }

        return $sampiyonlar;
    }

    /**
     * Kuponun tutup tutmadığı: true = kazandı, false = kaybetti, null = sonuç henüz belli değil.
     * Çok kazananlı market'ler (gol atanlar) de doğru çalışsın diye kupon bazlı değerlendirilir.
     */
    public function evaluate(FootballMatch $match, string $market, string $selection): ?bool
    {
        // Başkanın işaretlediği olaylar
        if ((Kehanet::MARKETS[$market]['source'] ?? 'auto') === 'event') {
            $olay = MatchEvent::where('match_id', $match->id)->where('event_key', $market)->first();

            if ($olay === null) {
                return null; // henüz girilmedi
            }

            return $olay->player_id === null
                ? false                                  // "kimse yok" işaretlendi → tüm kuponlar kaybeder
                : (string) $olay->player_id === $selection;
        }

        return match ($market) {
            'winner' => $selection === ($match->team_a_score === $match->team_b_score
                ? 'X'
                : ($match->team_a_score > $match->team_b_score ? 'A' : 'B')),

            'total_goals' => $selection === ((($match->team_a_score + $match->team_b_score) > app(OddsCalculator::class)->totalGoalsLine($match->group))
                ? 'over' : 'under'),

            'exact_score' => $selection === $match->team_a_score.'-'.$match->team_b_score,

            'clean_sheet' => $selection === ($match->team_b_score === 0
                ? 'A'
                : ($match->team_a_score === 0 ? 'B' : 'N')),

            'forma' => (string) $match->forma_goal_player_id === $selection,

            // Çok kazananlı: seçilen oyuncu gol attı mı / 2+ attı mı
            'scorer' => ($match->goals()->where('player_id', $selection)->value('count') ?? 0) >= 1,
            'brace' => ($match->goals()->where('player_id', $selection)->value('count') ?? 0) >= 2,

            'mvp' => $this->compareTop(
                $match->mvpVotes()->selectRaw('player_id, count(*) as agirlik')->groupBy('player_id')->get(),
                $selection,
                ! $match->mvpOpen(),
            ),

            'top_perf' => $this->compareTop(
                $match->performanceRatings()->selectRaw('player_id, avg(score) as agirlik')->groupBy('player_id')->get(),
                $selection,
                ! $match->perfOpen(),
            ),

            default => null,
        };
    }

    /**
     * Oylamayla belirlenen market'ler: oylama sürerken sonuç kesinleşmez.
     * Pencere kapandığında hiç oy yoksa kupon kaybeder.
     */
    protected function compareTop($rows, string $selection, bool $pencereKapandi): ?bool
    {
        if ($rows->isEmpty()) {
            return $pencereKapandi ? false : null;
        }

        if (! $pencereKapandi) {
            return null; // oylama sürüyor, lider değişebilir
        }

        return (string) ($rows->sortByDesc('agirlik')->first()->player_id) === $selection;
    }
}
