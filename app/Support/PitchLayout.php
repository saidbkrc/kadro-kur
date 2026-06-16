<?php

namespace App\Support;

/**
 * Saha diziliş hesaplayıcı.
 * Koordinat sistemi: 1000x560 viewBox, A takımı solda, B sağda.
 *
 * Kaleci kuralı (kullanıcı kararı): her takımda TAM 1 kaleci, kale asla boş kalmaz.
 *  1) birincil pozisyonu KL olan (en iyi kaleci özelliğiyle)
 *  2) yoksa KL'si ikincil olan biri
 *  3) o da yoksa defans oyuncularından biri (en düşük OVR'li — iyiler sahada kalsın)
 *  4) hiç yoksa son çare en düşük OVR'li oyuncu
 * Forvet kuralı: her takımda en az 1 forvet; doğal forvet yoksa orta sahadan
 * dribling+şut+teknik ortalaması en iyi oyuncu forvete çekilir.
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

    /** Otomatik: kaleci ayrılır, kalanlar birincil pozisyonlarına göre hatlara dağılır, forvet garanti edilir. */
    protected static function autoLayout(array $team, string $side): array
    {
        $keeper = self::selectKeeper($team);
        $outfield = self::without($team, $keeper);

        $groups = ['DEF' => [], 'OS' => [], 'FV' => []];
        foreach ($outfield as $player) {
            $groups[self::outfieldPrimary($player)][] = $player;
        }

        // En az 1 forvet garantisi: doğal forvet yoksa orta sahadan (yoksa defanstan) en iyi forveti çek
        if ($groups['FV'] === [] && $outfield !== []) {
            $source = $groups['OS'] !== [] ? 'OS' : 'DEF';
            if ($groups[$source] !== []) {
                usort($groups[$source], fn ($a, $b) => self::forwardScore($b['attrs'] ?? []) <=> self::forwardScore($a['attrs'] ?? []));
                $groups['FV'][] = array_shift($groups[$source]);
            }
        }

        return self::placeNodes($side, $keeper, $groups['DEF'], $groups['OS'], $groups['FV']);
    }

    /** Seçilen şablona göre: kaleci + def-orta-forvet hatları. */
    protected static function formationLayout(array $team, string $side, string $formation): array
    {
        $wants = array_map('intval', explode('-', $formation)); // [def, orta, forvet]

        return self::buildLines($team, $side, $wants);
    }

    /** Verilen [def, orta, forvet] sayılarına göre kadroyu hatlara yerleştirir (kaleci hariç). */
    protected static function buildLines(array $team, string $side, array $wants): array
    {
        $keeper = self::selectKeeper($team);
        $outfield = self::without($team, $keeper);
        $count = count($outfield);

        [$wantDef, $wantMid, $wantFwd] = [$wants[0], $wants[1], $wants[2]];

        // Hat kapasitelerini oyuncu sayısına uyarla
        $total = $wantDef + $wantMid + $wantFwd;
        while ($total > $count) {
            if ($wantMid > 0) {
                $wantMid--;
            } elseif ($wantDef > 1) {
                $wantDef--;
            } elseif ($wantFwd > 1) {
                $wantFwd--;
            } elseif ($wantDef > 0) {
                $wantDef--;
            } else {
                $wantFwd--;
            }
            $total--;
        }
        while ($total < $count) {
            $wantMid++;
            $total++;
        }

        // En az 1 forvet garantisi (yeterli oyuncu varsa)
        if ($wantFwd === 0 && $count >= 2) {
            if ($wantMid > 1) {
                $wantMid--;
                $wantFwd++;
            } elseif ($wantDef > 1) {
                $wantDef--;
                $wantFwd++;
            }
        }

        $rest = $outfield;
        $def = self::pickLine($rest, 'DEF', $wantDef);
        $fwd = self::pickLine($rest, 'FV', $wantFwd);
        $mid = $rest; // kalanlar orta saha

        return self::placeNodes($side, $keeper, $def, $mid, $fwd);
    }

    /**
     * Takımın kalecisini seçer (kale asla boş kalmaz).
     *
     * @return array oyuncu düğümü
     */
    protected static function selectKeeper(array $team): array
    {
        // 1) Birincil pozisyonu KL olanlar — en iyi kaleci özelliğine sahip olan
        $primary = array_values(array_filter($team, fn (array $p) => ($p['positions'][0] ?? null) === 'KL'));
        if ($primary !== []) {
            return self::bestBy($primary, fn (array $p) => Attributes::weightedScore($p['attrs'] ?? [], 'KL'));
        }

        // 2) KL'si ikincil olan biri
        $klAny = array_values(array_filter($team, fn (array $p) => in_array('KL', $p['positions'] ?? [], true)));
        if ($klAny !== []) {
            return self::bestBy($klAny, fn (array $p) => Attributes::weightedScore($p['attrs'] ?? [], 'KL'));
        }

        // 3) Defans oyuncularından biri — en düşük OVR'li (iyi defanslar sahada kalsın)
        $defs = array_values(array_filter($team, fn (array $p) => in_array('DEF', $p['positions'] ?? [], true)));
        if ($defs !== []) {
            return self::bestBy($defs, fn (array $p) => -($p['ovr'] ?? 0));
        }

        // 4) Son çare: herhangi biri (en düşük OVR'li)
        return self::bestBy($team, fn (array $p) => -($p['ovr'] ?? 0));
    }

    /** Bir hattı doldurur: doğal oyuncular önce (öncelik sırasına göre), sonra terfi adayları. */
    protected static function pickLine(array &$rest, string $pos, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        usort($rest, fn (array $a, array $b) => self::lineScore($b, $pos) <=> self::lineScore($a, $pos));
        $chosen = array_slice($rest, 0, $count);
        $rest = array_slice($rest, $count);

        return $chosen;
    }

    /** Bir oyuncunun bir hatta uygunluğu. Doğal pozisyonlar her zaman terfi adaylarından önce gelir. */
    protected static function lineScore(array $player, string $pos): float
    {
        $positions = $player['positions'] ?? [];
        $rank = array_search($pos, $positions, true);

        if ($rank !== false) {
            // Doğal oyuncu: öncelik sırası belirleyici (1. > 2. > 3.), sonra hattaki ağırlıklı puan
            return 1000 - $rank * 100 + Attributes::weightedScore($player['attrs'] ?? [], $pos);
        }

        // Terfi adayı: forvete dribling+şut+teknik ortalaması, diğer hatlara ağırlıklı puanın yarısı
        if ($pos === 'FV') {
            return self::forwardScore($player['attrs'] ?? []);
        }

        return Attributes::weightedScore($player['attrs'] ?? [], $pos) * 0.5;
    }

    /** Forvet uygunluğu: dribling + şut + teknik ortalaması (eksik özellik 5 sayılır). */
    protected static function forwardScore(array $attrs): float
    {
        return ((float) ($attrs['dribling'] ?? 5)
            + (float) ($attrs['sut'] ?? 5)
            + (float) ($attrs['teknik'] ?? 5)) / 3;
    }

    /** Kaleci dışında bir oyuncunun yerleşeceği saha hattı (sadece KL ise orta sahaya). */
    protected static function outfieldPrimary(array $player): string
    {
        foreach ($player['positions'] ?? [] as $pos) {
            if (in_array($pos, ['DEF', 'OS', 'FV'], true)) {
                return $pos;
            }
        }

        return 'OS';
    }

    /** @return list en yüksek skorlu oyuncu düğümü */
    protected static function bestBy(array $items, callable $score): array
    {
        usort($items, fn (array $a, array $b) => $score($b) <=> $score($a));

        return $items[0];
    }

    /** Bir oyuncuyu takımdan id'ye göre çıkarır. */
    protected static function without(array $team, array $player): array
    {
        return array_values(array_filter($team, fn (array $p) => $p['id'] !== $player['id']));
    }

    /** Kaleci + üç hattı saha üzerine yerleştirir. */
    protected static function placeNodes(string $side, array $keeper, array $def, array $mid, array $fwd): array
    {
        $xs = $side === 'A'
            ? ['KL' => 60, 'DEF' => 175, 'OS' => 300, 'FV' => 430]
            : ['KL' => self::W - 60, 'DEF' => self::W - 175, 'OS' => self::W - 300, 'FV' => self::W - 430];

        $nodes = [];
        $place = function (array $line, float $x) use (&$nodes, $side): void {
            // Hat içinde dikey sıra: solaklar sol kanada, sağ ayaklılar sağ kanada;
            // aynı kanat tercihinde OVR yüksek olan öne. (Tek kişilik hatta etkisiz.)
            usort($line, function (array $a, array $b) use ($side) {
                return [self::flankKey($a, $side), -($a['ovr'] ?? 0)]
                    <=> [self::flankKey($b, $side), -($b['ovr'] ?? 0)];
            });

            $count = count($line);
            $spacing = $count > 1 ? min(125, (self::H - 130) / ($count - 1)) : 0;

            foreach ($line as $i => $player) {
                $y = $count === 1 ? self::H / 2 : self::H / 2 + ($i - ($count - 1) / 2) * $spacing;
                $nodes[] = self::node($player, $x, $y);
            }
        };

        $place([$keeper], $xs['KL']);
        $place($def, $xs['DEF']);
        $place($mid, $xs['OS']);
        $place($fwd, $xs['FV']);

        return $nodes;
    }

    /**
     * Kanat sıralama anahtarı (küçük = yukarı/ekranın üstü).
     * A takımı sağa hücum eder: sol kanat üstte (küçük y), sağ kanat altta.
     * B takımı sola hücum eder: yön ters döner. Çift ayak ortada.
     */
    protected static function flankKey(array $player, string $side): int
    {
        $foot = $player['foot'] ?? 'right';

        $order = $side === 'A'
            ? ['left' => 0, 'both' => 1, 'right' => 2]
            : ['right' => 0, 'both' => 1, 'left' => 2];

        return $order[$foot] ?? 1;
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
