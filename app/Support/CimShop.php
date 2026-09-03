<?php

namespace App\Support;

/**
 * Çim mağazası: kozmetik ürünler. Oyun içi hiçbir avantaj sağlamaz —
 * sadece görünüm. Amacı biriken Çim'e bir gider yaratmak (musluk/gider dengesi).
 *
 * type: frame (kart çerçevesi) | color (isim rengi) | title (unvan)
 */
class CimShop
{
    public const ITEMS = [
        // 🖼️ Kart çerçeveleri — oyuncu kartının kenarı ve parıltısı
        'frame_ates' => [
            'type' => 'frame', 'name' => 'Ateş Çerçevesi', 'icon' => '🔥', 'price' => 400,
            'desc' => 'Kartın turuncu alevle çevrelenir.',
            'class' => 'border-[#FF7A1A] shadow-[0_0_35px_rgba(255,122,26,.35)]',
        ],
        'frame_buz' => [
            'type' => 'frame', 'name' => 'Buz Çerçevesi', 'icon' => '🧊', 'price' => 400,
            'desc' => 'Soğuk mavi bir hâle.',
            'class' => 'border-[#7CD4FF] shadow-[0_0_35px_rgba(124,212,255,.35)]',
        ],
        'frame_zumrut' => [
            'type' => 'frame', 'name' => 'Zümrüt Çerçevesi', 'icon' => '💚', 'price' => 600,
            'desc' => 'Sahanın yeşili kartına yansır.',
            'class' => 'border-[#C8F04B] shadow-[0_0_35px_rgba(200,240,75,.35)]',
        ],
        'frame_elmas' => [
            'type' => 'frame', 'name' => 'Elmas Çerçeve', 'icon' => '💎', 'price' => 1200,
            'desc' => 'Beyaz-mor parıltılı, nadir görünüm.',
            'class' => 'border-[#D9C7FF] shadow-[0_0_45px_rgba(217,199,255,.45)]',
        ],
        'frame_efsane' => [
            'type' => 'frame', 'name' => 'Efsane Çerçeve', 'icon' => '👑', 'price' => 2500,
            'desc' => 'Altın gradyan — mağazanın en üstü.',
            'class' => 'border-gold shadow-[0_0_55px_rgba(255,200,61,.55)] ring-1 ring-gold/40',
        ],

        // 🎨 İsim renkleri — profilde ve listelerde adın
        'color_gold' => ['type' => 'color', 'name' => 'Altın İsim', 'icon' => '🟡', 'price' => 300, 'desc' => 'Adın altın renginde yazılır.', 'class' => 'text-gold'],
        'color_ates' => ['type' => 'color', 'name' => 'Ateş İsim', 'icon' => '🟠', 'price' => 300, 'desc' => 'Adın turuncu yanar.', 'class' => 'text-[#FF7A1A]'],
        'color_buz' => ['type' => 'color', 'name' => 'Buz İsim', 'icon' => '🔵', 'price' => 300, 'desc' => 'Adın buz mavisi.', 'class' => 'text-[#7CD4FF]'],
        'color_mor' => ['type' => 'color', 'name' => 'Mor İsim', 'icon' => '🟣', 'price' => 500, 'desc' => 'Adın mor parlar.', 'class' => 'text-[#C8A2FF]'],

        // 🏷️ Unvanlar — profilde adının altında görünür
        'title_efsane' => ['type' => 'title', 'name' => 'Efsane', 'icon' => '🐐', 'price' => 800, 'desc' => 'Profilinde "Efsane" yazar.', 'text' => 'Efsane'],
        'title_kral' => ['type' => 'title', 'name' => 'Sahanın Kralı', 'icon' => '👑', 'price' => 1000, 'desc' => 'Profilinde "Sahanın Kralı" yazar.', 'text' => 'Sahanın Kralı'],
        'title_kahin' => ['type' => 'title', 'name' => 'Çim Baronu', 'icon' => '🌱', 'price' => 1500, 'desc' => 'Profilinde "Çim Baronu" yazar.', 'text' => 'Çim Baronu'],
        'title_duvar' => ['type' => 'title', 'name' => 'Aşılmaz', 'icon' => '🧱', 'price' => 700, 'desc' => 'Profilinde "Aşılmaz" yazar.', 'text' => 'Aşılmaz'],
    ];

    public const TYPES = [
        'frame' => ['icon' => '🖼️', 'name' => 'Kart Çerçeveleri', 'hint' => 'Oyuncu kartının kenarı'],
        'color' => ['icon' => '🎨', 'name' => 'İsim Renkleri', 'hint' => 'Adının rengi'],
        'title' => ['icon' => '🏷️', 'name' => 'Unvanlar', 'hint' => 'Profilinde adının altında'],
    ];

    /** Türe göre gruplanmış katalog. */
    public static function grouped(): array
    {
        $out = [];

        foreach (self::ITEMS as $key => $item) {
            $out[$item['type']][$key] = $item;
        }

        return $out;
    }

    /** Kuşanılan ürünün CSS sınıfı / metni (yoksa varsayılan). */
    public static function value(?string $key, string $field, string $default = ''): string
    {
        return self::ITEMS[$key][$field] ?? $default;
    }
}
