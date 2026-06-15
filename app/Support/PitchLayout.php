<?php

namespace App\Support;

/**
 * Saha diziliş hesaplayıcı — kadro.html'deki layout/layoutFormation portu.
 * Koordinat sistemi: 1000x560 viewBox, A takımı solda, B sağda.
 */
class PitchLayout
{
    public const W = 1000;

    public const H = 560;

    /**
     * @param  list<array{id: int, name: string, number: ?int, positions: list<string>, ovr: float, attrs: array}>  $team
     * @param  'A'|'B'  $side
     * @param  ?string  $formation  "3-1-2" gibi; null/auto = otomatik
     * @param  array<int|string, array{x: float, y: float}>  $overrides  sürükle-bırakla verilen konumlar
     * @return list<array{id: int, name: string, number: ?int, ovr: float, x: float, y: float}>
     */
    public static function layout(array $team, string $side, ?string $formation, array $overrides = []): array
    {
        if ($team === []) {
            return [];
        }

        $nodes = ($formation !== null && in_array($formation, Attributes::FORMATIONS, true))
            ? self::formationLayout($team, $side, $formation)
            : self::autoLayout($team, $side);

        // Elle taşınan oyuncular kayıtlı konumunda kalır
        return array_map(function (array $node) use ($overrides) {
            $saved = $overrides[$node['id']] ?? $overrides[(string) $node['id']] ?? null;

            if (is_array($saved) && isset($saved['x'], $saved['y'])) {
                $node['x'] = (float) $saved['x'];
                $node['y'] = (float) $saved['y'];
            }

            return $node;
        }, $nodes);
    }

    /** Otomatik: birincil pozisyona göre hatlara dağıt. */
    protected static function autoLayout(array $team, string $side): array
    {
        usort($team, fn (array $a, array $b) => $b['ovr'] <=> $a['ovr']);

        $groups = ['KL' => [], 'DEF' => [], 'OS' => [], 'FV' => []];
        foreach ($team as $player) {
            $primary = $player['positions'][0] ?? 'OS';
            $groups[$primary][] = $player;
        }

        $xs = $side === 'A'
            ? ['KL' => 60, 'DEF' => 165, 'OS' => 295, 'FV' => 415]
            : ['KL' => self::W - 60, 'DEF' => self::W - 165, 'OS' => self::W - 295, 'FV' => self::W - 415];

        $nodes = [];
        foreach ($groups as $pos => $players) {
            $count = count($players);
            if ($count === 0) {
                continue;
            }

            $spacing = $count > 1 ? min(96, (self::H - 140) / ($count - 1)) : 0;
            foreach ($players as $i => $player) {
                $y = $count === 1 ? self::H / 2 : self::H / 2 + ($i - ($count - 1) / 2) * $spacing;
                $nodes[] = self::node($player, $xs[$pos], $y);
            }
        }

        return $nodes;
    }

    /** Seçilen şablona göre: kaleci + def-orta-forvet hatları. */
    protected static function formationLayout(array $team, string $side, string $formation): array
    {
        $want = array_map('intval', explode('-', $formation)); // [def, orta, forvet]

        // Kaleci: gerçek kaleci varsa en iyisi; yoksa klasik kural — en düşük puanlı kaleye :)
        $keepers = array_values(array_filter($team, fn (array $p) => in_array('KL', $p['positions'], true)));
        usort($keepers, fn (array $a, array $b) => $b['ovr'] <=> $a['ovr']);

        if ($keepers !== []) {
            $gk = $keepers[0];
        } else {
            $sorted = $team;
            usort($sorted, fn (array $a, array $b) => $a['ovr'] <=> $b['ovr']);
            $gk = $sorted[0];
        }

        $rest = array_values(array_filter($team, fn (array $p) => $p['id'] !== $gk['id']));

        // Hat kapasitelerini oyuncu sayısına uyarla (eksikse önce forvetten kıs, fazlaysa ortaya ekle)
        $total = array_sum($want);
        while ($total > count($rest)) {
            if ($want[2] > 0) {
                $want[2]--;
            } elseif ($want[1] > 0) {
                $want[1]--;
            } else {
                $want[0]--;
            }
            $total--;
        }
        while ($total < count($rest)) {
            $want[1]++;
            $total++;
        }

        // Hatta uygunluk: pozisyon önceliği belirleyici (1. > 2. > 3.), sonra o hattaki ağırlıklı puan
        $lineScore = function (array $p, string $pos): float {
            $rank = array_search($pos, $p['positions'], true);
            $base = $rank !== false
                ? 110 - $rank * 30
                : ($pos !== 'OS' && in_array('OS', $p['positions'], true) ? 20 : 0);

            return $base + Attributes::weightedScore($p['attrs'], $pos);
        };

        $pick = function (string $pos, int $k) use (&$rest, $lineScore): array {
            usort($rest, fn (array $a, array $b) => $lineScore($b, $pos) <=> $lineScore($a, $pos));
            $chosen = array_slice($rest, 0, $k);
            $rest = array_slice($rest, $k);

            return $chosen;
        };

        $def = $pick('DEF', $want[0]);
        $fwd = $pick('FV', $want[2]);
        $mid = $rest; // kalanlar orta saha

        $xs = $side === 'A' ? [60, 175, 300, 430] : [self::W - 60, self::W - 175, self::W - 300, self::W - 430];

        $nodes = [];
        $place = function (array $line, float $x) use (&$nodes): void {
            usort($line, fn (array $a, array $b) => $b['ovr'] <=> $a['ovr']);
            $count = count($line);
            $spacing = $count > 1 ? min(125, (self::H - 130) / ($count - 1)) : 0;

            foreach ($line as $i => $player) {
                $y = $count === 1 ? self::H / 2 : self::H / 2 + ($i - ($count - 1) / 2) * $spacing;
                $nodes[] = self::node($player, $x, $y);
            }
        };

        $place([$gk], $xs[0]);
        $place($def, $xs[1]);
        $place($mid, $xs[2]);
        $place($fwd, $xs[3]);

        return $nodes;
    }

    protected static function node(array $player, float $x, float $y): array
    {
        return [
            'id' => $player['id'],
            'name' => $player['name'],
            'number' => $player['number'],
            'ovr' => $player['ovr'],
            'ovr_public' => $player['ovr_public'] ?? true,
            'x' => round($x, 1),
            'y' => round($y, 1),
        ];
    }
}
