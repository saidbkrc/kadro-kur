<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Denk takım kurucu — eski kadro.html'deki algoritmanın PHP portu.
 *
 * Tüm olası bölünmeleri dener (ilk oyuncu A'da sabitlenir, simetrik kopyaları eler);
 * her bölünmeyi ortalama OVR farkı + pozisyon kapsama cezası + kural cezası ile
 * puanlar ve en iyi N alternatifi döndürür. Kural ihlali 1000 ceza puanı olduğu
 * için kurallara uyan bir bölünme varsa mutlaka öne geçer.
 */
class TeamBalancer
{
    public const MIN_PLAYERS = 4;

    public const MAX_PLAYERS = 24;

    /** Asıl kalecilerin (birincil pozisyon KL) eşit bölünmesi — neredeyse şart (OVR'yi ezer, kurallara yenilir). */
    public const KEEPER_BALANCE_WEIGHT = 200.0;

    /** Kaleci olabilen (KL içeren) oyuncuların dağılımı — yumuşak tercih. */
    public const KEEPER_SOFT_WEIGHT = 25.0;

    /**
     * @param  list<array{id: int, positions: list<string>, ovr: float}>  $players
     * @param  list<array{type: 'apart'|'together', a: int, b: int}>  $rules
     * @return list<array{score: float, a: list<int>, b: list<int>}> en iyi bölünmeler (skora göre sıralı)
     */
    public function balance(array $players, array $rules = [], int $keep = 8): array
    {
        $players = array_values($players);
        $n = count($players);

        if ($n < self::MIN_PLAYERS) {
            throw new InvalidArgumentException('Denge kurmak için en az '.self::MIN_PLAYERS.' oyuncu gerekli.');
        }
        if ($n > self::MAX_PLAYERS) {
            throw new InvalidArgumentException('En fazla '.self::MAX_PLAYERS.' oyuncu ile denge kurulabilir.');
        }

        $half = intdiv($n, 2);
        $best = [];
        $idx = [];

        $recurse = function (int $start, int $need) use (&$recurse, &$best, &$idx, $players, $rules, $n, $keep): void {
            if ($need === 0) {
                $inA = array_flip($idx);
                $teamA = [$players[0]];
                $teamB = [];

                for ($i = 1; $i < $n; $i++) {
                    if (isset($inA[$i])) {
                        $teamA[] = $players[$i];
                    } else {
                        $teamB[] = $players[$i];
                    }
                }

                $score = $this->splitScore($teamA, $teamB, $rules);

                if (count($best) < $keep || $score < $best[count($best) - 1]['score']) {
                    $best[] = [
                        'score' => $score,
                        'a' => array_column($teamA, 'id'),
                        'b' => array_column($teamB, 'id'),
                    ];
                    usort($best, fn (array $x, array $y) => $x['score'] <=> $y['score']);
                    if (count($best) > $keep) {
                        array_pop($best);
                    }
                }

                return;
            }

            for ($i = $start; $i <= $n - $need; $i++) {
                $idx[] = $i;
                $recurse($i + 1, $need - 1);
                array_pop($idx);
            }
        };

        $recurse(1, $half - 1);

        return $best;
    }

    /** Bölünme skoru: düşük = iyi. Kural ihlali başına 1000 ceza. */
    protected function splitScore(array $teamA, array $teamB, array $rules): float
    {
        $avg = function (array $team): float {
            $sum = array_sum(array_column($team, 'ovr'));

            return $sum / max(1, count($team));
        };

        $avgDiff = abs($avg($teamA) - $avg($teamB));

        $coverA = $this->positionCoverage($teamA);
        $coverB = $this->positionCoverage($teamB);

        $penalty = 0.0;

        // Asıl kaleciler (birincil pozisyon KL) iki takıma eşit bölünmeli — neredeyse şart.
        $penalty += self::KEEPER_BALANCE_WEIGHT
            * $this->imbalanceExcess($this->primaryKeeperCount($teamA), $this->primaryKeeperCount($teamB));

        // Kaleci olabilen (KL içeren) oyuncular da mümkünse dağılsın — yumuşak.
        $penalty += self::KEEPER_SOFT_WEIGHT
            * $this->imbalanceExcess($coverA['KL'], $coverB['KL']);

        foreach (['DEF', 'OS', 'FV'] as $pos) {
            $penalty += abs($coverA[$pos] - $coverB[$pos]) * 0.6;
        }

        return $avgDiff * 10 + $penalty + $this->rulesPenalty($teamA, $teamB, $rules);
    }

    /** Birincil pozisyonu kaleci olan oyuncu sayısı. */
    protected function primaryKeeperCount(array $team): int
    {
        return count(array_filter($team, fn (array $p) => ($p['positions'][0] ?? null) === 'KL'));
    }

    /**
     * İki takım arasındaki sayı dengesizliğinin "fazlası".
     * Tek sayıda toplamda 1 fark normaldir (eşit bölünemez); onun üstü cezalandırılır.
     */
    protected function imbalanceExcess(int $a, int $b): int
    {
        return max(0, abs($a - $b) - (($a + $b) % 2));
    }

    /** Takımdaki pozisyon kapsama sayıları (çoklu pozisyonlar hepsine sayılır). */
    protected function positionCoverage(array $team): array
    {
        $cover = ['KL' => 0, 'DEF' => 0, 'OS' => 0, 'FV' => 0];

        foreach ($team as $player) {
            foreach ($player['positions'] as $pos) {
                if (isset($cover[$pos])) {
                    $cover[$pos]++;
                }
            }
        }

        return $cover;
    }

    protected function rulesPenalty(array $teamA, array $teamB, array $rules): float
    {
        $inA = array_flip(array_column($teamA, 'id'));
        $inB = array_flip(array_column($teamB, 'id'));

        $penalty = 0.0;

        foreach ($rules as $rule) {
            $aHere = isset($inA[$rule['a']]) || isset($inB[$rule['a']]);
            $bHere = isset($inA[$rule['b']]) || isset($inB[$rule['b']]);

            // İki oyuncusu da kadroda olmayan kural değerlendirilmez
            if (! $aHere || ! $bHere) {
                continue;
            }

            $sameTeam = (isset($inA[$rule['a']]) && isset($inA[$rule['b']]))
                || (isset($inB[$rule['a']]) && isset($inB[$rule['b']]));

            if ($rule['type'] === 'apart' && $sameTeam) {
                $penalty += 1000;
            }
            if ($rule['type'] === 'together' && ! $sameTeam) {
                $penalty += 1000;
            }
        }

        return $penalty;
    }
}
