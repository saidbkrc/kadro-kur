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

    public function test_asil_kaleciler_dengelemede_ayri_takimlara_boluner(): void
    {
        // id 1 ve 2 birincil kaleci, kalanlar orta saha
        $players = [
            ['id' => 1, 'positions' => ['KL'], 'ovr' => 6.0],
            ['id' => 2, 'positions' => ['KL'], 'ovr' => 6.0],
        ];
        foreach ([3, 4, 5, 6] as $id) {
            $players[] = ['id' => $id, 'positions' => ['OS'], 'ovr' => 6.0];
        }

        $best = (new TeamBalancer)->balance($players)[0];

        $this->assertNotSame(
            in_array(1, $best['a'], true),
            in_array(2, $best['a'], true),
            'İki asıl kaleci ayrı takımlara düşmeli',
        );
    }

    private function pitchPlayer(int $id, array $positions, float $ovr = 6.0, array $attrs = [], string $foot = 'right'): array
    {
        return ['id' => $id, 'name' => "P{$id}", 'number' => null, 'positions' => $positions, 'foot' => $foot, 'ovr' => $ovr, 'attrs' => $attrs];
    }

    /** A takımında kaleci sütunundaki (x=60) düğüm. */
    private function keeperNode(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if (abs($node['x'] - 60) < 0.5) {
                return $node;
            }
        }

        return null;
    }

    public function test_dizilis_kaleci_ve_forvet_kurallari(): void
    {
        // 7 kişilik takım, 3-1-2: kaleci + 3 def + 1 orta + 2 forvet = 7 node
        $team = [];
        foreach ([['KL'], ['DEF'], ['DEF'], ['OS'], ['OS'], ['FV'], ['FV']] as $i => $positions) {
            $team[] = $this->pitchPlayer($i + 1, $positions);
        }
        $nodes = PitchLayout::layout($team, 'A', '3-1-2');
        $this->assertCount(7, $nodes);
        foreach ($nodes as $node) {
            $this->assertLessThan(PitchLayout::W / 2, $node['x']); // A sol yarıda
        }

        // Elle taşınan konum korunur
        $moved = collect(PitchLayout::layout($team, 'A', '3-1-2', [1 => ['x' => 333.0, 'y' => 111.0]]))->firstWhere('id', 1);
        $this->assertSame(333.0, $moved['x']);
        $this->assertSame(111.0, $moved['y']);
    }

    public function test_iki_kaleci_ayni_takimda_yan_yana_durmaz(): void
    {
        // İki birincil kaleci aynı takımda; id1 daha iyi kaleci
        $team = [
            $this->pitchPlayer(1, ['KL'], 7.0, ['refleks' => 9, 'ucma' => 9, 'topkontrol' => 9, 'gkpas' => 9, 'pozalma' => 9]),
            $this->pitchPlayer(2, ['KL'], 5.0),
            $this->pitchPlayer(3, ['DEF']),
            $this->pitchPlayer(4, ['OS']),
            $this->pitchPlayer(5, ['FV']),
        ];

        $nodes = PitchLayout::layout($team, 'A', null);

        // Kaleci sütununda tam 1 oyuncu, o da daha iyi kaleci (id1)
        $atKeeper = array_filter($nodes, fn ($n) => abs($n['x'] - 60) < 0.5);
        $this->assertCount(1, $atKeeper);
        $this->assertSame(1, $this->keeperNode($nodes)['id']);

        // Diğer kaleci (id2) saha içinde, kaleci sütununda değil
        $two = collect($nodes)->firstWhere('id', 2);
        $this->assertGreaterThan(60, $two['x']);
    }

    public function test_kalesiz_takimda_defans_kaleye_gecer(): void
    {
        // Hiç kaleci yok; id1 en düşük OVR'li defans → kaleye geçmeli
        $team = [
            $this->pitchPlayer(1, ['DEF'], 4.0),
            $this->pitchPlayer(2, ['DEF'], 7.0),
            $this->pitchPlayer(3, ['OS'], 6.0),
            $this->pitchPlayer(4, ['FV'], 6.0),
        ];

        $keeper = $this->keeperNode(PitchLayout::layout($team, 'A', null));
        $this->assertNotNull($keeper, 'Kale asla boş kalmamalı');
        $this->assertSame(1, $keeper['id']); // en düşük OVR'li defans
    }

    public function test_ayaga_gore_kanat_yerlesimi(): void
    {
        // 3 defans: solak, çift, sağ ayak — aynı OVR
        $team = [
            $this->pitchPlayer(1, ['KL']),
            $this->pitchPlayer(2, ['DEF'], 6.0, [], 'left'),
            $this->pitchPlayer(3, ['DEF'], 6.0, [], 'both'),
            $this->pitchPlayer(4, ['DEF'], 6.0, [], 'right'),
            $this->pitchPlayer(5, ['FV']),
        ];

        // A takımı: sol kanat üstte (küçük y) → solak en üstte, sağ ayaklı en altta
        $nodesA = collect(PitchLayout::layout($team, 'A', null))->keyBy('id');
        $this->assertLessThan($nodesA[3]['y'], $nodesA[2]['y'], 'A: solak çiftin üstünde');
        $this->assertLessThan($nodesA[4]['y'], $nodesA[3]['y'], 'A: çift sağ ayaklının üstünde');

        // B takımı: yön ters → sağ ayaklı en üstte, solak en altta
        $nodesB = collect(PitchLayout::layout($team, 'B', null))->keyBy('id');
        $this->assertLessThan($nodesB[3]['y'], $nodesB[4]['y'], 'B: sağ ayaklı çiftin üstünde');
        $this->assertLessThan($nodesB[2]['y'], $nodesB[3]['y'], 'B: çift solağın üstünde');
    }

    public function test_forvetsiz_takimda_en_iyi_ortasaha_forvete_cekilir(): void
    {
        // Forvet yok; id4 dribling/şut/teknik'te en iyi orta saha → forvete çekilmeli
        $team = [
            $this->pitchPlayer(1, ['KL']),
            $this->pitchPlayer(2, ['DEF']),
            $this->pitchPlayer(3, ['OS'], 6.0, ['dribling' => 4, 'sut' => 4, 'teknik' => 4]),
            $this->pitchPlayer(4, ['OS'], 6.0, ['dribling' => 9, 'sut' => 9, 'teknik' => 9]),
            $this->pitchPlayer(5, ['OS'], 6.0, ['dribling' => 5, 'sut' => 5, 'teknik' => 5]),
        ];

        $nodes = PitchLayout::layout($team, 'A', null);

        // Forvet sütununda (x=430) bir oyuncu olmalı ve o id4 olmalı
        $atFwd = collect($nodes)->first(fn ($n) => abs($n['x'] - 430) < 0.5);
        $this->assertNotNull($atFwd, 'Takımda en az 1 forvet olmalı');
        $this->assertSame(4, $atFwd['id']);
    }
}
