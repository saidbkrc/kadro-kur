<?php

namespace App\Support;

/**
 * Halı saha mevkileri — başkan atar (oyuncu başına en çok 2, sıra = öncelik).
 * Her mevki bir hatta (KL/DEF/OS/FV — Attributes::POSITIONS) ve bir kanada bağlıdır:
 *  - hat: kadro dengesi ve saha dizilişi bu hatla çalışır (eski sistemle uyumlu)
 *  - kanat: L/R → bek ve kanatlar sahada doğru kenara yerleşir; null = merkez
 *  - group: dengelemede birbirinin yerine sayılan mevkiler (sol/sağ bek = "bek")
 *
 * Oyuncunun kendi seçtiği genel pozisyon (positions) puanlama özelliklerini
 * belirlemeye devam eder; mevki yalnızca kadro kurma ve dizilişi etkiler.
 */
final class Roles
{
    public const ALL = [
        'KL' => ['name' => 'Kaleci', 'short' => 'KL', 'line' => 'KL', 'side' => null, 'group' => 'KL'],
        'STP' => ['name' => 'Stoper', 'short' => 'STP', 'line' => 'DEF', 'side' => null, 'group' => 'STP'],
        'SLB' => ['name' => 'Sol Bek', 'short' => 'SLB', 'line' => 'DEF', 'side' => 'L', 'group' => 'BEK'],
        'SGB' => ['name' => 'Sağ Bek', 'short' => 'SĞB', 'line' => 'DEF', 'side' => 'R', 'group' => 'BEK'],
        'OLB' => ['name' => 'Ön Libero', 'short' => 'ÖL', 'line' => 'OS', 'side' => null, 'group' => 'ORTA'],
        'MO' => ['name' => 'Orta Saha', 'short' => 'OS', 'line' => 'OS', 'side' => null, 'group' => 'ORTA'],
        'SLK' => ['name' => 'Sol Kanat', 'short' => 'SLK', 'line' => 'OS', 'side' => 'L', 'group' => 'KANAT'],
        'SGK' => ['name' => 'Sağ Kanat', 'short' => 'SĞK', 'line' => 'OS', 'side' => 'R', 'group' => 'KANAT'],
        'SF' => ['name' => 'Santrafor', 'short' => 'SF', 'line' => 'FV', 'side' => null, 'group' => 'SF'],
    ];

    /** Oyuncu başına atanabilecek en çok mevki. */
    public const MAX_PER_PLAYER = 2;

    /** Geçersizleri ayıklar, tekrarı kaldırır, sınırı uygular. */
    public static function clean(?array $roles): array
    {
        return array_slice(array_values(array_unique(array_filter(
            (array) $roles,
            fn ($r) => is_string($r) && array_key_exists($r, self::ALL),
        ))), 0, self::MAX_PER_PLAYER);
    }

    /** Mevkilerden türeyen hatlar (sırası korunur, tekrarsız): ['SLB','SLK'] → ['DEF','OS']. */
    public static function lines(array $roles): array
    {
        return array_values(array_unique(array_map(fn ($r) => self::ALL[$r]['line'], self::clean($roles))));
    }

    /** Birincil mevkinin kanadı: 'L' | 'R' | null (merkez / mevki yok). */
    public static function side(array $roles): ?string
    {
        $ilk = self::clean($roles)[0] ?? null;

        return $ilk === null ? null : self::ALL[$ilk]['side'];
    }
}
