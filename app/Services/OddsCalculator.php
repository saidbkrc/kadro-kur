<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\MatchEvent;
use App\Models\Rsvp;
use App\Support\Kehanet;
use Illuminate\Support\Facades\DB;

/**
 * Oran motoru. Geçmiş maç verisinden olasılık türetir; veri azken
 * ön varsayıma (prior) yaslanır, veri biriktikçe gözleme kayar —
 * yani "başta sabit, sonra otomatik" davranışı tek formülden gelir.
 *
 * Laplace düzeltmesi: p = (gözlem + prior_basari) / (deneme + prior_agirlik)
 */
class OddsCalculator
{
    /** Grup başına önbellek: [group_id => ['played' => [...], 'goals' => [...], ...]] */
    protected array $cache = [];

    /** Bir seçim için ondalık oran (1.85 gibi). */
    public function odds(FootballMatch $match, string $market, string $selection): float
    {
        $p = max(0.001, min(0.99, $this->probability($match, $market, $selection)));

        return round(max(Kehanet::MIN_ODDS, min(Kehanet::maxOdds($market), 1 / $p)), 2);
    }

    /** Seçimin gerçekleşme olasılığı (0-1). */
    public function probability(FootballMatch $match, string $market, string $selection): float
    {
        $kind = Kehanet::MARKETS[$market]['kind'] ?? 'oyuncu';

        return match ($kind) {
            'takim' => $this->teamProbability($match, $market, $selection),
            'altust' => $this->totalGoalsProbability($match, $selection),
            'skor' => $this->exactScoreProbability($match, $selection),
            default => $this->playerProbability($match, $market, (int) $selection),
        };
    }

    /* ---------- takım market'leri ---------- */

    protected function teamProbability(FootballMatch $match, string $market, string $selection): float
    {
        [$avgA, $avgB] = $this->teamStrengths($match);
        $fark = $avgA - $avgB;

        // Elo benzeri: 1 puanlık üstünlük ≈ %64 kazanma
        $pA = 1 / (1 + pow(10, -$fark / 4));

        if ($market === 'clean_sheet') {
            // Rakibin beklenen golü → hiç gol atamama olasılığı (Poisson)
            $ortalama = $this->groupAverageGoals($match->group) / 2;
            $lambdaB = max(0.3, $ortalama * (1 - ($pA - 0.5)));   // A güçlüyse B az atar
            $lambdaA = max(0.3, $ortalama * (1 + ($pA - 0.5)));

            return match ($selection) {
                'A' => exp(-$lambdaB),                  // Turuncu gol yemez = B gol atamaz
                'B' => exp(-$lambdaA),
                default => 1 - exp(-$lambdaB) - exp(-$lambdaA) + exp(-$lambdaA - $lambdaB),
            };
        }

        // Beraberlik payı: takımlar denkse artar
        $pX = 0.22 * (1 - min(1, abs($fark) / 2));

        return match ($selection) {
            'X' => $pX,
            'A' => $pA * (1 - $pX),
            default => (1 - $pA) * (1 - $pX),
        };
    }

    /** Kadro ortalama puanları; kadro kurulmadıysa gelenlerin ortalaması. */
    protected function teamStrengths(FootballMatch $match): array
    {
        $rsvps = $match->rsvps()->with('player.attributeRatings')
            ->where('status', 'going')->whereNull('waitlist_position')->get();

        $ort = fn ($takim) => $takim->isEmpty()
            ? 6.5
            : round($takim->avg(fn (Rsvp $r) => $r->player->displayRating()), 2);

        $a = $rsvps->where('team', 'A');
        $b = $rsvps->where('team', 'B');

        return $a->isEmpty() || $b->isEmpty()
            ? [$ort($rsvps), $ort($rsvps)]   // kadro yoksa denk kabul et
            : [$ort($a), $ort($b)];
    }

    /* ---------- toplam gol ---------- */

    /**
     * Alt/üst eşiği: grubun gol ortalamasından türetilir ve DAİMA .5 ile biter —
     * böylece toplam gol eşiğe eşit olamaz, beraberlik durumu oluşmaz.
     */
    public function totalGoalsLine(Group $group): float
    {
        return max(3.5, floor($this->groupAverageGoals($group)) + 0.5);
    }

    protected function totalGoalsProbability(FootballMatch $match, string $selection): float
    {
        $lambda = max(1.0, $this->groupAverageGoals($match->group));
        $line = $this->totalGoalsLine($match->group);

        // P(toplam <= floor(line)) — Poisson kümülatif
        $altOlasilik = 0.0;
        for ($k = 0; $k <= floor($line); $k++) {
            $altOlasilik += exp(-$lambda) * pow($lambda, $k) / $this->faktoriyel($k);
        }

        return $selection === 'under' ? $altOlasilik : 1 - $altOlasilik;
    }

    /** Skor tam tahmini: her takımın golü bağımsız Poisson kabul edilir. Seçim "4-2" biçiminde. */
    protected function exactScoreProbability(FootballMatch $match, string $selection): float
    {
        [$a, $b] = array_pad(array_map('intval', explode('-', $selection)), 2, 0);

        if ($a < 0 || $b < 0 || $a > 20 || $b > 20) {
            return 0.001;
        }

        [$avgA, $avgB] = $this->teamStrengths($match);
        $toplam = max(2.0, $this->groupAverageGoals($match->group));
        $fark = ($avgA - $avgB) / 10;

        $lambdaA = max(0.3, $toplam / 2 * (1 + $fark));
        $lambdaB = max(0.3, $toplam / 2 * (1 - $fark));

        $poisson = fn (float $l, int $k) => exp(-$l) * pow($l, $k) / $this->faktoriyel($k);

        return $poisson($lambdaA, $a) * $poisson($lambdaB, $b);
    }

    protected function faktoriyel(int $n): float
    {
        $s = 1.0;
        for ($i = 2; $i <= $n; $i++) {
            $s *= $i;
        }

        return $s;
    }

    /** Grubun maç başına ortalama toplam golü (veri yoksa 8). */
    protected function groupAverageGoals(Group $group): float
    {
        $c = $this->counts($group);

        return $c['avg_goals'];
    }

    /* ---------- oyuncu market'leri ---------- */

    protected function playerProbability(FootballMatch $match, string $market, int $playerId): float
    {
        $c = $this->counts($match->group);
        $oynadi = $c['played'][$playerId] ?? 0;
        $kadro = max(6, $match->rsvps()->where('status', 'going')->whereNull('waitlist_position')->count());

        // Gol market'leri: maç başına gol oranı → Poisson
        if ($market === 'scorer' || $market === 'brace') {
            $gol = $c['goals'][$playerId] ?? 0;
            $lambda = ($gol + 0.8) / ($oynadi + 2.5);          // düzeltilmiş gol/maç
            $enAzBir = 1 - exp(-$lambda);

            return $market === 'scorer'
                ? $enAzBir
                : 1 - exp(-$lambda) * (1 + $lambda);           // P(>=2)
        }

        // Tekil ödül market'leri (MVP, forma, performans, manuel olaylar):
        // maç başına 1 kişi kazanır → düzeltilmiş oran, kadroya göre ön varsayım
        $sayac = match ($market) {
            'mvp' => $c['mvp'][$playerId] ?? 0,
            'forma' => $c['forma'][$playerId] ?? 0,
            'top_perf' => $c['top_perf'][$playerId] ?? 0,
            default => $c['events'][$market][$playerId] ?? 0,
        };

        return ($sayac + 1) / ($oynadi + $kadro);
    }

    /* ---------- veri toplama ---------- */

    /** Grubun geçmiş sayaçları (tek sorgu turu, örnek başına önbellekli). */
    protected function counts(Group $group): array
    {
        if (isset($this->cache[$group->id])) {
            return $this->cache[$group->id];
        }

        $macIdler = $group->matches()->where('status', 'completed')->pluck('id');

        $played = DB::table('rsvps')->whereIn('match_id', $macIdler)
            ->where('status', 'going')->whereNull('waitlist_position')
            ->selectRaw('player_id, count(*) as c')->groupBy('player_id')->pluck('c', 'player_id')->all();

        $goals = DB::table('goals')->whereIn('match_id', $macIdler)
            ->selectRaw('player_id, sum(count) as c')->groupBy('player_id')->pluck('c', 'player_id')->all();

        $mvp = DB::table('mvp_votes')->whereIn('match_id', $macIdler)
            ->selectRaw('match_id, player_id, count(*) as oy')->groupBy('match_id', 'player_id')->get()
            ->groupBy('match_id')
            ->map(fn ($rows) => $rows->sortByDesc('oy')->first()?->player_id)
            ->filter()->countBy()->all();

        $forma = $group->matches()->where('status', 'completed')
            ->whereNotNull('forma_goal_player_id')
            ->pluck('forma_goal_player_id')->countBy()->all();

        $topPerf = DB::table('match_performance_ratings')->whereIn('match_id', $macIdler)
            ->selectRaw('match_id, player_id, avg(score) as ort')->groupBy('match_id', 'player_id')->get()
            ->groupBy('match_id')
            ->map(fn ($rows) => $rows->sortByDesc('ort')->first()?->player_id)
            ->filter()->countBy()->all();

        $events = MatchEvent::whereIn('match_id', $macIdler)->whereNotNull('player_id')->get()
            ->groupBy('event_key')
            ->map(fn ($rows) => $rows->countBy('player_id')->all())
            ->all();

        $skorlar = $group->matches()->where('status', 'completed')
            ->selectRaw('COALESCE(team_a_score,0) + COALESCE(team_b_score,0) as toplam')->pluck('toplam');

        return $this->cache[$group->id] = [
            'played' => $played,
            'goals' => $goals,
            'mvp' => $mvp,
            'forma' => $forma,
            'top_perf' => $topPerf,
            'events' => $events,
            'avg_goals' => $skorlar->isEmpty() ? 8.0 : max(2.0, round($skorlar->avg(), 1)),
        ];
    }
}
