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
        // 🖼️ Kart çerçeveleri — oyuncu kartının kenarında maskelenmiş gradyan halka.
        // Renkler --cim-a / --cim-b değişkenleriyle geçilir (bkz. app.css).
        'frame_ates' => [
            'type' => 'frame', 'name' => 'Ateş Çerçevesi', 'icon' => '🔥', 'price' => 400,
            'desc' => 'Turuncudan altına geçen halka, sıcak bir parıltı.',
            'class' => 'cim-frame cim-frame-glow border-transparent [--cim-a:#FF7A1A] [--cim-b:#FFC83D]',
        ],
        'frame_buz' => [
            'type' => 'frame', 'name' => 'Buz Çerçevesi', 'icon' => '🧊', 'price' => 400,
            'desc' => 'Soğuk mavi halka ve buğulu bir hâle.',
            'class' => 'cim-frame cim-frame-glow border-transparent [--cim-a:#7CD4FF] [--cim-b:#BFE9FF]',
        ],
        'frame_zumrut' => [
            'type' => 'frame', 'name' => 'Zümrüt Çerçevesi', 'icon' => '💚', 'price' => 600,
            'desc' => 'Sahanın iki yeşili arasında geçiş yapan halka.',
            'class' => 'cim-frame cim-frame-glow border-transparent [--cim-a:#C8F04B] [--cim-b:#28AD55]',
        ],
        'frame_elmas' => [
            'type' => 'frame', 'name' => 'Elmas Çerçeve', 'icon' => '💎', 'price' => 1200,
            'desc' => 'Mor-mavi halka kartın çevresinde yavaşça döner.',
            'class' => 'cim-frame cim-frame-spin cim-frame-glow border-transparent [--cim-a:#D9C7FF] [--cim-b:#7CD4FF]',
        ],
        'frame_holo' => [
            'type' => 'frame', 'name' => 'Holografik Çerçeve', 'icon' => '🪞', 'price' => 1800,
            'desc' => 'Tüm tayfı dolaşan halka kartın çevresinde döner.',
            'class' => 'cim-frame cim-frame-spin cim-frame-rainbow cim-frame-glow border-transparent [--cim-a:#C8A2FF]',
        ],
        'frame_efsane' => [
            'type' => 'frame', 'name' => 'Efsane Çerçeve', 'icon' => '👑', 'price' => 2500,
            'desc' => 'Dönen altın halka + nefes alan parıltı. Mağazanın en üstü.',
            'class' => 'cim-frame cim-frame-spin cim-frame-pulse border-transparent [--cim-a:#FFC83D] [--cim-b:#FF7A1A]',
        ],

        // 🎨 İsim renkleri — profilde ve listelerde adın. Düz renkler giriş
        // kademesi; gradyanlılar üst kademe (metin gradyanla boyanır ve kayar).
        'color_gold' => ['type' => 'color', 'name' => 'Altın İsim', 'icon' => '🟡', 'price' => 300, 'desc' => 'Adın altın renginde yazılır.', 'class' => 'text-gold', 'hex' => '#FFC83D'],
        'color_ates' => ['type' => 'color', 'name' => 'Ateş İsim', 'icon' => '🟠', 'price' => 300, 'desc' => 'Adın turuncu yanar.', 'class' => 'text-[#FF7A1A]', 'hex' => '#FF7A1A'],
        'color_buz' => ['type' => 'color', 'name' => 'Buz İsim', 'icon' => '🔵', 'price' => 300, 'desc' => 'Adın buz mavisi.', 'class' => 'text-[#7CD4FF]', 'hex' => '#7CD4FF'],
        'color_mor' => ['type' => 'color', 'name' => 'Mor İsim', 'icon' => '🟣', 'price' => 500, 'desc' => 'Adın mor parlar.', 'class' => 'text-[#C8A2FF]', 'hex' => '#C8A2FF'],
        'color_alev' => [
            'type' => 'color', 'name' => 'Alev Geçişi', 'icon' => '🔥', 'price' => 900,
            'desc' => 'Adın turuncudan altına akan bir gradyanla yazılır ve parlar.',
            'class' => 'cim-name-grad cim-name-shine [--cim-a:#FF7A1A] [--cim-b:#FFC83D]', 'hex' => '#FF7A1A',
        ],
        'color_cim' => [
            'type' => 'color', 'name' => 'Çim Gradyanı', 'icon' => '🌱', 'price' => 900,
            'desc' => 'Sahanın iki yeşili adında akar.',
            'class' => 'cim-name-grad cim-name-shine [--cim-a:#C8F04B] [--cim-b:#28AD55]', 'hex' => '#C8F04B',
        ],
        'color_holo' => [
            'type' => 'color', 'name' => 'Holografik İsim', 'icon' => '🪞', 'price' => 1500,
            'desc' => 'Mavi-mor gradyan adın üzerinde sürekli kayar.',
            'class' => 'cim-name-grad cim-name-shine [--cim-a:#7CD4FF] [--cim-b:#C8A2FF]', 'hex' => '#A9C9FF',
        ],
        'color_altin_parlak' => [
            'type' => 'color', 'name' => 'Parlayan Altın', 'icon' => '✨', 'price' => 2000,
            'desc' => 'Altın adının üzerinden sürekli bir ışık geçer. İsim renklerinin en üstü.',
            'class' => 'cim-name-grad cim-name-shine [--cim-a:#FFC83D] [--cim-b:#FFFFFF]', 'hex' => '#FFC83D',
        ],

        // 🏷️ Unvanlar — Çim servetinin göstergesi, profilde adının altında
        'title_toplayici' => ['type' => 'title', 'name' => 'Çim Toplayıcısı', 'icon' => '🪙', 'price' => 500, 'desc' => 'Yolun başı — profilinde "Çim Toplayıcısı" yazar.', 'text' => 'Çim Toplayıcısı'],
        'title_musrif' => ['type' => 'title', 'name' => 'Çim Müsrifi', 'icon' => '💸', 'price' => 800, 'desc' => 'Kazandığını harcayanlara — "Çim Müsrifi".', 'text' => 'Çim Müsrifi'],
        'title_yatirimci' => ['type' => 'title', 'name' => 'Çim Yatırımcısı', 'icon' => '📈', 'price' => 1200, 'desc' => 'Kuponu bilerek oynayanlara — "Çim Yatırımcısı".', 'text' => 'Çim Yatırımcısı'],
        'title_baron' => ['type' => 'title', 'name' => 'Çim Baronu', 'icon' => '🌱', 'price' => 1500, 'desc' => 'Profilinde "Çim Baronu" yazar.', 'text' => 'Çim Baronu'],
        'title_patron' => ['type' => 'title', 'name' => 'Çim Patronu', 'icon' => '🎩', 'price' => 2500, 'desc' => 'Kasanın sahibi — "Çim Patronu".', 'text' => 'Çim Patronu'],
        'title_milyoner' => ['type' => 'title', 'name' => 'Çim Milyoneri', 'icon' => '💰', 'price' => 5000, 'desc' => 'Mağazanın en pahalısı — "Çim Milyoneri".', 'text' => 'Çim Milyoneri'],

        // ⚽ Saha rozetleri — diziliş görselinde oyuncu diskinin köşesinde görünür
        'pitch_yildiz' => ['type' => 'pitch', 'name' => 'Yıldız', 'icon' => '⭐', 'price' => 400, 'desc' => 'Sahada adının yanında yıldız.', 'text' => '⭐'],
        'pitch_alev' => ['type' => 'pitch', 'name' => 'Alev', 'icon' => '🔥', 'price' => 400, 'desc' => 'Formda olduğunu herkes görsün.', 'text' => '🔥'],
        'pitch_simsek' => ['type' => 'pitch', 'name' => 'Şimşek', 'icon' => '⚡', 'price' => 600, 'desc' => 'Hız senin işin.', 'text' => '⚡'],
        'pitch_tac' => ['type' => 'pitch', 'name' => 'Taç', 'icon' => '👑', 'price' => 1000, 'desc' => 'Sahanın kralı sensin.', 'text' => '👑'],
        'pitch_keci' => ['type' => 'pitch', 'name' => 'Keçi', 'icon' => '🐐', 'price' => 1500, 'desc' => 'GOAT — tartışmaya kapalı.', 'text' => '🐐'],
    ];

    public const TYPES = [
        'pitch' => ['icon' => '⚽', 'name' => 'Saha Rozetleri', 'hint' => 'Diziliş görselinde diskinin köşesinde'],
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
