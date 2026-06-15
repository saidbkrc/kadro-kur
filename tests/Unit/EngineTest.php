<?php

namespace Tests\Unit;

use App\Services\TeamBalancer;
use App\Support\Attributes;
use App\Support\PitchLayout;
use PHPUnit\Framework\TestCase;

class EngineTest extends TestCase
{
    public function test_puanlanacak_ozellikler_pozisyona_gore(): void
    {
        // Kaleci: genel + kaleci özellikleri (yeni davranış)
        $gk = Attributes::forPositions(['KL']);
        $this->assertArrayHasKey('hiz', $gk);
        $this->assertArrayHasKey('refleks', $gk);
        $this->assertArrayNotHasKey('bitiricilik', $gk);

        // Çoklu pozisyon: genel + her pozisyonun özel özellikleri
        $osfv = Attributes::forPositions(['OS', 'FV']);
        $this->assertArrayHasKey('oyunkurma', $osfv);
        $this->assertArrayHasKey('bitiricilik', $osfv);
        $this->assertArrayNotHasKey('topkesme', $osfv);
        $this->assertArrayNotHasKey('refleks', $osfv);

        // Kaleci + saha pozisyonu kombinasyonu: genel + kaleci + defans özellikleri
        $kldef = Attributes::forPositions(['KL', 'DEF']);
        $this->assertArrayHasKey('hiz', $kldef);
        $this->assertArrayHasKey('refleks', $kldef);
        $this->assertArrayHasKey('topkesme', $kldef);
        $this->assertArrayNotHasKey('pres', $kldef);
    }

    public function test_ovr_hesabi(): void
    {
        // Hiç puan yoksa orta seviye
        $this->assertEqualsWithDelta(5.0, Attributes::overall([], ['OS']), 0.001);
        $this->assertEqualsWithDelta(5.0, Attributes::overall([], ['KL']), 0.001);

        // Tüm özellikler 8 → OVR 8 (ağırlıklardan bağımsız)
        $all8 = array_fill_keys(
            array_merge(
                array_keys(Attributes::GENERAL),
                array_keys(Attributes::SPEC['DEF']),
                array_keys(Attributes::SPEC['OS']),
                array_keys(Attributes::SPEC['FV']),
                array_keys(Attributes::GK),
            ),
            8,
        );
        $this->assertEqualsWithDelta(8.0, Attributes::overall($all8, ['OS', 'FV']), 0.001);
        $this->assertEqualsWithDelta(8.0, Attributes::overall($all8, ['KL']), 0.001);
        $this->assertEqualsWithDelta(8.0, Attributes::overall($all8, ['KL', 'DEF']), 0.001);

        // Forvet için şut, pastan daha ağır basar
        $shooter = Attributes::overall(['sut' => 10] + array_fill_keys(array_keys(Attributes::GENERAL), 5), ['FV']);
        $passer = Attributes::overall(['pas' => 10] + array_fill_keys(array_keys(Attributes::GENERAL), 5), ['FV']);
        $this->assertGreaterThan($passer, $shooter);
    }

    public function test_dengeleme_kurallara_uyar(): void
    {
        $players = [];
        foreach ([1 => 9.0, 2 => 8.0, 3 => 7.0, 4 => 6.0, 5 => 5.0, 6 => 4.0] as $id => $ovr) {
            $players[] = ['id' => $id, 'positions' => ['OS'], 'ovr' => $ovr];
        }

        $balancer = new TeamBalancer;

        // Kuralsız: en iyi bölünme dengeli olmalı
        $best = $balancer->balance($players)[0];
        $this->assertCount(3, $best['a']);
        $this->assertCount(3, $best['b']);
        $this->assertLessThan(10, $best['score']); // kural cezası yok

        // "1 ile 2 ayrı takımlarda" kuralı uygulanır
        $best = $balancer->balance($players, [['type' => 'apart', 'a' => 1, 'b' => 2]])[0];
        $oneInA = in_array(1, $best['a'], true);
        $twoInA = in_array(2, $best['a'], true);
        $this->assertNotSame($oneInA, $twoInA);

        // "1 ile 2 aynı takımda" kuralı uygulanır
        $best = $balancer->balance($players, [['type' => 'together', 'a' => 1, 'b' => 2]])[0];
        $this->assertSame(in_array(1, $best['a'], true), in_array(2, $best['a'], true));
    }

    public function test_dizilis_hesabi(): void
    {
        $team = [];
        foreach ([['KL'], ['DEF'], ['DEF'], ['OS'], ['OS'], ['FV'], ['FV']] as $i => $positions) {
            $team[] = [
                'id' => $i + 1,
                'name' => 'Oyuncu '.($i + 1),
                'number' => null,
                'positions' => $positions,
                'ovr' => 6.0,
                'attrs' => [],
            ];
        }

        // 3-1-2: kaleci + 3 def + 1 orta + 2 forvet = 7 node
        $nodes = PitchLayout::layout($team, 'A', '3-1-2');
        $this->assertCount(7, $nodes);

        // A takımı sol yarıda
        foreach ($nodes as $node) {
            $this->assertLessThan(PitchLayout::W / 2, $node['x']);
        }

        // Elle taşınan konum korunur
        $nodes = PitchLayout::layout($team, 'A', '3-1-2', [1 => ['x' => 333.0, 'y' => 111.0]]);
        $moved = collect($nodes)->firstWhere('id', 1);
        $this->assertSame(333.0, $moved['x']);
        $this->assertSame(111.0, $moved['y']);
    }
}
