<?php

namespace App\Support;

/**
 * Özellik ve pozisyon sabitleri + OVR hesabı.
 * Ağırlık tabloları eski kadro.html'den birebir taşındı.
 */
class Attributes
{
    public const POSITIONS = ['KL' => 'Kaleci', 'DEF' => 'Defans', 'OS' => 'Orta Saha', 'FV' => 'Forvet'];

    /** Tercih edilen ayak — saha dizilişinde kanat yerleşimini belirler. */
    public const FEET = ['right' => 'Sağ ayak', 'left' => 'Sol ayak', 'both' => 'Çift ayak'];

    public const FORMATIONS = ['3-1-2', '3-2-1', '2-3-1', '2-2-2', '2-1-3'];

    /** Genel özellikler — kaleciler dahil herkes için listelenir. */
    public const GENERAL = [
        'hiz' => 'HIZ',
        'sut' => 'ŞUT',
        'pas' => 'PAS',
        'teknik' => 'TEKNİK',
        'dribling' => 'DRİBLİNG',
        'fizik' => 'FİZİK',
        'kondisyon' => 'KONDİSYON',
        'zeka' => 'OYUN ZEKASI',
    ];

    /** Pozisyona özel özellikler — o pozisyon seçilince eklenir. */
    public const SPEC = [
        'DEF' => ['topkesme' => 'TOP KESME', 'adamtutma' => 'ADAM TUTMA', 'havatopu' => 'HAVA TOPU'],
        'OS' => ['oyunkurma' => 'OYUN KURMA', 'topkapma' => 'TOP KAPMA', 'pres' => 'PRES'],
        'FV' => ['bitiricilik' => 'BİTİRİCİLİK', 'topsuzkosu' => 'TOPSUZ KOŞU', 'ilkdokunus' => 'İLK DOKUNUŞ'],
    ];

    /** Kaleciye özel özellikler. */
    public const GK = [
        'refleks' => 'REFLEKS',
        'ucma' => 'UÇMA',
        'topkontrol' => 'TOP KONTROL',
        'gkpas' => 'PAS',
        'pozalma' => 'POZİSYON ALMA',
    ];

    /** Pozisyona göre özellik ağırlıkları (genel + o pozisyonun özel özellikleri). */
    public const POS_WEIGHTS = [
        'DEF' => [
            'hiz' => 1.5, 'sut' => 0.5, 'pas' => 1.5, 'teknik' => 1.0, 'dribling' => 0.5,
            'fizik' => 2.0, 'kondisyon' => 1.5, 'zeka' => 1.5,
            'topkesme' => 3.0, 'adamtutma' => 3.0, 'havatopu' => 2.0,
        ],
        'OS' => [
            'hiz' => 1.5, 'sut' => 1.5, 'pas' => 3.0, 'teknik' => 2.0, 'dribling' => 1.5,
            'fizik' => 1.0, 'kondisyon' => 2.0, 'zeka' => 2.5,
            'oyunkurma' => 3.0, 'topkapma' => 2.0, 'pres' => 1.5,
        ],
        'FV' => [
            'hiz' => 2.5, 'sut' => 3.0, 'pas' => 1.5, 'teknik' => 1.5, 'dribling' => 2.5,
            'fizik' => 1.0, 'kondisyon' => 1.5, 'zeka' => 2.0,
            'bitiricilik' => 3.0, 'topsuzkosu' => 2.0, 'ilkdokunus' => 2.0,
        ],
    ];

    public const GK_WEIGHTS = ['refleks' => 3.0, 'ucma' => 2.5, 'topkontrol' => 1.5, 'gkpas' => 1.5, 'pozalma' => 2.5];

    /** Kalecinin OVR'sinde genel özelliklerin ağırlığı (yeni: eskiden hiç sayılmazdı). */
    public const GK_GENERAL_WEIGHT = 1.0;

    /** Pozisyon önceliği ağırlıkları: 1. pozisyon 3x, 2. 2x, 3. 1x. */
    public const PRIORITY_WEIGHTS = [3, 2, 1];

    /**
     * Bir oyuncunun puanlanacağı özellikler: [key => etiket].
     * Genel özellikler herkes için; KL seçiliyse kaleci özellikleri,
     * diğer her pozisyon için o pozisyonun özel özellikleri eklenir
     * (KL + DEF gibi kombinasyonlar serbesttir).
     */
    public static function forPositions(array $positions): array
    {
        $attrs = self::GENERAL;

        foreach ($positions as $pos) {
            $attrs += $pos === 'KL' ? self::GK : (self::SPEC[$pos] ?? []);
        }

        return $attrs;
    }

    /** Pozisyonun ağırlık profili. KL: kaleci özellikleri ağır + genel özellikler 1.0. */
    public static function weightsFor(string $pos): array
    {
        if ($pos === 'KL') {
            return self::GK_WEIGHTS + array_fill_keys(array_keys(self::GENERAL), self::GK_GENERAL_WEIGHT);
        }

        return self::POS_WEIGHTS[$pos] ?? [];
    }

    /** Tek pozisyon için ağırlıklı puan. Eksik özellik 5 (orta) kabul edilir. */
    public static function weightedScore(array $attrs, string $pos): float
    {
        $sum = 0.0;
        $total = 0.0;

        foreach (self::weightsFor($pos) as $key => $weight) {
            $sum += (float) ($attrs[$key] ?? 5) * $weight;
            $total += $weight;
        }

        return $total > 0 ? $sum / $total : 5.0;
    }

    /** Genel puan (OVR): pozisyon profillerinin öncelik ağırlıklı (3x/2x/1x) ortalaması. */
    public static function overall(array $attrs, array $positions): float
    {
        if ($positions === []) {
            $keys = array_keys(self::GENERAL);

            return array_sum(array_map(fn (string $k) => (float) ($attrs[$k] ?? 5), $keys)) / count($keys);
        }

        $sum = 0.0;
        $total = 0.0;

        foreach (array_values($positions) as $i => $pos) {
            $weight = self::PRIORITY_WEIGHTS[$i] ?? 1;
            $sum += self::weightedScore($attrs, $pos) * $weight;
            $total += $weight;
        }

        return $sum / $total;
    }
}
