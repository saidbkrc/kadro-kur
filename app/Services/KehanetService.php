<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Models\Prediction;
use App\Models\User;
use App\Support\Kehanet;
use Illuminate\Support\Facades\DB;

/**
 * Kehanet: kupon yapma, haftalık Çim yüklemesi ve sonuçlandırma.
 * Çim sanaldır — gerçek parayla ilişkisi yoktur.
 */
class KehanetService
{
    /** Haftalık yükleme: bu hafta verilmediyse ekler. İlk kez giren başlangıç bakiyesi alır. */
    public function grantWeeklyIfDue(User $user): int
    {
        $ilkKez = $user->cim_granted_at === null;

        if (! $ilkKez && $user->cim_granted_at->gte(now()->startOfWeek())) {
            return 0;
        }

        $miktar = $ilkKez ? Kehanet::STARTING_BALANCE : Kehanet::WEEKLY_GRANT;

        $user->forceFill([
            'cim_balance' => $user->cim_balance + $miktar,
            'cim_granted_at' => now(),
        ])->save();

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

        return DB::transaction(function () use ($user, $match, $market, $selection, $stake) {
            $user = User::lockForUpdate()->find($user->id);

            $eski = Prediction::where('user_id', $user->id)
                ->where('match_id', $match->id)
                ->where('market_key', $market)
                ->where('status', 'pending')
                ->first();

            $iade = $eski?->stake ?? 0;

            if ($user->cim_balance + $iade < $stake) {
                return ['ok' => false, 'message' => 'Yeterli Çim yok.'];
            }

            $odds = app(OddsCalculator::class)->odds($match, $market, $selection);

            $eski?->delete();

            Prediction::create([
                'user_id' => $user->id,
                'match_id' => $match->id,
                'market_key' => $market,
                'selection' => $selection,
                'odds' => $odds,
                'stake' => $stake,
            ]);

            $user->forceFill(['cim_balance' => $user->cim_balance + $iade - $stake])->save();

            return ['ok' => true, 'message' => "Kupon yapıldı — oran {$odds}×"];
        });
    }

    /** Kuponu iptal eder, tutarı iade eder (maç başlamadıysa). */
    public function cancelBet(User $user, Prediction $prediction): array
    {
        if ($prediction->user_id !== $user->id || $prediction->status !== 'pending') {
            return ['ok' => false, 'message' => 'Bu kupon iptal edilemez.'];
        }

        if (! $this->bettingOpen($prediction->match)) {
            return ['ok' => false, 'message' => 'Maç başladı, kupon iptal edilemez.'];
        }

        DB::transaction(function () use ($user, $prediction) {
            $u = User::lockForUpdate()->find($user->id);
            $u->forceFill(['cim_balance' => $u->cim_balance + $prediction->stake])->save();
            $prediction->delete();
        });

        return ['ok' => true, 'message' => 'Kupon iptal edildi, Çim iade edildi.'];
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

        foreach (Prediction::where('match_id', $match->id)->where('status', 'pending')->get() as $kupon) {
            $tuttu = $this->evaluate($match, $kupon->market_key, $kupon->selection);

            if ($tuttu === null) {
                continue; // sonuç henüz belli değil (başkan işaretlemedi) — beklemede kalır
            }

            $odeme = $tuttu ? (int) round($kupon->stake * (float) $kupon->odds) : 0;

            DB::transaction(function () use ($kupon, $tuttu, $odeme) {
                $kupon->update([
                    'status' => $tuttu ? 'won' : 'lost',
                    'payout' => $odeme,
                    'settled_at' => now(),
                ]);

                if ($odeme > 0) {
                    $u = User::lockForUpdate()->find($kupon->user_id);
                    $u->forceFill(['cim_balance' => $u->cim_balance + $odeme])->save();
                }
            });

            $sayac++;
        }

        return $sayac;
    }

    /** İptal edilen maçta tüm kuponları geçersiz sayar, tutarları iade eder. */
    public function voidMatch(FootballMatch $match, string $sebep = ''): int
    {
        $sayac = 0;

        foreach (Prediction::where('match_id', $match->id)->where('status', 'pending')->get() as $kupon) {
            DB::transaction(function () use ($kupon) {
                $kupon->update(['status' => 'void', 'payout' => $kupon->stake, 'settled_at' => now()]);
                $u = User::lockForUpdate()->find($kupon->user_id);
                $u->forceFill(['cim_balance' => $u->cim_balance + $kupon->stake])->save();
            });
            $sayac++;
        }

        return $sayac;
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
