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

        // 🔒 Şarta bağlı ürünler — Çim yetmez, sahada hak etmen gerekir.
        // 'requires' => ['badge' => <PlayerBadges anahtarı>, 'label' => insan diliyle koşul]
        'pitch_duvar' => [
            'type' => 'pitch', 'name' => 'Duvar', 'icon' => '🧱', 'price' => 800, 'text' => '🧱',
            'desc' => 'Kaleyi kapatanlara. Sahada adının yanında tuğla duvar.',
            'requires' => ['badge' => 'wall', 'label' => 'Kalede gol yemeden bir maç bitirmiş olmak'],
        ],
        'title_simsek' => [
            'type' => 'title', 'name' => 'Şimşek', 'icon' => '⚡', 'price' => 1200, 'text' => 'Şimşek',
            'desc' => 'Hat-trick yapanlara açılır — profilinde "Şimşek" yazar.',
            'requires' => ['badge' => 'hat_trick', 'label' => 'Bir maçta 3 gol atmış olmak'],
        ],
        'frame_kral' => [
            'type' => 'frame', 'name' => 'Kral Çerçevesi', 'icon' => '🦁', 'price' => 3000,
            'desc' => 'Yalnızca gol kralları alabilir. Kırmızı-altın halka döner.',
            'class' => 'cim-frame cim-frame-spin cim-frame-pulse border-transparent [--cim-a:#FF3B3B] [--cim-b:#FFC83D]',
            'requires' => ['badge' => 'goal_king', 'label' => 'Toplam 50 gol atmış olmak'],
        ],

        // 📅 Sınırlı ürün — yalnızca belirli ayda satışta, her yıl tekrar açılır.
        'frame_sezon' => [
            'type' => 'frame', 'name' => 'Sezon Açılışı', 'icon' => '📅', 'price' => 1500, 'only_month' => 9,
            'desc' => 'Sadece Eylül boyunca satışta. Kaçırırsan seneye.',
            'class' => 'cim-frame cim-frame-spin cim-frame-glow border-transparent [--cim-a:#28AD55] [--cim-b:#FFC83D]',
        ],

        // 👕 Forma desenleri — diziliş görselinde oyuncu diski. 'pattern' anahtarı
        // SVG'de tanımlı desene karşılık gelir (takım rengi korunur, desen değişir).
        'kit_cizgili' => ['type' => 'kit', 'name' => 'Çizgili Forma', 'icon' => '👕', 'price' => 500, 'pattern' => 'cizgili', 'desc' => 'Klasik dikey çizgiler. Diskin sahada çizgili görünür.'],
        'kit_enine' => ['type' => 'kit', 'name' => 'Enine Bantlı', 'icon' => '🎽', 'price' => 500, 'pattern' => 'enine', 'desc' => 'Yatay bantlı forma deseni.'],
        'kit_capraz' => ['type' => 'kit', 'name' => 'Çapraz Bant', 'icon' => '🏳️', 'price' => 900, 'pattern' => 'capraz', 'desc' => 'Göğüsten geçen çapraz bant.'],
        'kit_yarim' => ['type' => 'kit', 'name' => 'Yarım Yarım', 'icon' => '🔲', 'price' => 900, 'pattern' => 'yarim', 'desc' => 'Diskin yarısı koyu, yarısı takım rengi.'],
        'kit_kareli' => ['type' => 'kit', 'name' => 'Kareli', 'icon' => '🏁', 'price' => 1400, 'pattern' => 'kareli', 'desc' => 'Damalı desen — uzaktan bile belli olur.'],

        // 🎉 Gol sevinçleri — attığın golün yanında görünür
        'cel_kollar' => ['type' => 'celebration', 'name' => 'Kollar Havada', 'icon' => '🙌', 'price' => 400, 'text' => '🙌', 'desc' => 'Golünün yanında kollar havada.'],
        'cel_kalp' => ['type' => 'celebration', 'name' => 'Kalp', 'icon' => '❤️', 'price' => 400, 'text' => '❤️', 'desc' => 'Golünü sevdiklerine adarsın.'],
        'cel_sus' => ['type' => 'celebration', 'name' => 'Susturma', 'icon' => '🤫', 'price' => 700, 'text' => '🤫', 'desc' => 'Konuşanları susturursun.'],
        'cel_roket' => ['type' => 'celebration', 'name' => 'Roket', 'icon' => '🚀', 'price' => 900, 'text' => '🚀', 'desc' => 'Gol değil füze.'],
        'cel_soguk' => ['type' => 'celebration', 'name' => 'Buz Gibi', 'icon' => '🥶', 'price' => 1200, 'text' => '🥶', 'desc' => 'Soğukkanlı bitiriş.'],

        // 🖼️ Avatar halkaları — profil fotoğrafının çevresi
        'avatar_altin' => [
            'type' => 'avatar', 'name' => 'Altın Halka', 'icon' => '🟡', 'price' => 600,
            'desc' => 'Fotoğrafının çevresi altın parlar.',
            'class' => 'cim-frame cim-frame-glow [--cim-a:#FFC83D] [--cim-b:#FF7A1A]',
        ],
        'avatar_buz' => [
            'type' => 'avatar', 'name' => 'Buz Halka', 'icon' => '🧊', 'price' => 600,
            'desc' => 'Soğuk mavi bir halka.',
            'class' => 'cim-frame cim-frame-glow [--cim-a:#7CD4FF] [--cim-b:#BFE9FF]',
        ],
        'avatar_tayf' => [
            'type' => 'avatar', 'name' => 'Tayf Halkası', 'icon' => '🌈', 'price' => 1600,
            'desc' => 'Fotoğrafının çevresinde dönen tayf.',
            'class' => 'cim-frame cim-frame-spin cim-frame-rainbow cim-frame-glow [--cim-a:#C8A2FF]',
        ],

        // 🏆 Rozet vitrini — kazandığın rozetlerden seçtiklerini profilinde öne çıkarır.
        // Satın alınan şey slot sayısıdır; rozetler zaten sahada kazanılmıştır.
        'showcase_3' => ['type' => 'showcase', 'name' => 'Rozet Vitrini', 'icon' => '🏆', 'price' => 1000, 'slots' => 3, 'desc' => 'Profilinde 3 rozetini öne çıkar.'],
        'showcase_5' => ['type' => 'showcase', 'name' => 'Geniş Vitrin', 'icon' => '🏛️', 'price' => 2200, 'slots' => 5, 'desc' => 'Profilinde 5 rozetini öne çıkar.'],
    ];

    /**
     * Nadirlik kademeleri. Ürüne 'rarity' yazılmadıysa fiyattan türetilir —
     * böylece yeni ürün eklerken ayrıca işaretlemek gerekmez.
     */
    public const RARITIES = [
        'yaygin' => ['name' => 'Yaygın', 'class' => 'text-pitch-muted border-pitch-line'],
        'nadir' => ['name' => 'Nadir', 'class' => 'text-[#7CD4FF] border-[#7CD4FF]/50'],
        'destansi' => ['name' => 'Destansı', 'class' => 'text-[#C8A2FF] border-[#C8A2FF]/50'],
        'efsanevi' => ['name' => 'Efsanevi', 'class' => 'text-gold border-gold/60'],
    ];

    public const TYPES = [
        'pitch' => ['icon' => '⚽', 'name' => 'Saha Rozetleri', 'hint' => 'Diziliş görselinde diskinin köşesinde'],
        'kit' => ['icon' => '👕', 'name' => 'Forma Desenleri', 'hint' => 'Diziliş görselinde diskinin deseni'],
        'frame' => ['icon' => '🖼️', 'name' => 'Kart Çerçeveleri', 'hint' => 'Oyuncu kartının kenarı'],
        'avatar' => ['icon' => '📷', 'name' => 'Avatar Halkaları', 'hint' => 'Profil fotoğrafının çevresi'],
        'color' => ['icon' => '🎨', 'name' => 'İsim Renkleri', 'hint' => 'Adının rengi'],
        'title' => ['icon' => '🏷️', 'name' => 'Unvanlar', 'hint' => 'Profilinde adının altında'],
        'celebration' => ['icon' => '🎉', 'name' => 'Gol Sevinçleri', 'hint' => 'Attığın golün yanında'],
        'showcase' => ['icon' => '🏆', 'name' => 'Rozet Vitrini', 'hint' => 'Profilinde öne çıkardığın rozetler'],
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

    /** Ürünün nadirlik kademesi — açıkça yazılmadıysa fiyattan türetilir. */
    public static function rarity(string $key): string
    {
        $urun = self::ITEMS[$key] ?? null;

        if ($urun === null) {
            return 'yaygin';
        }

        if (isset($urun['rarity'])) {
            return $urun['rarity'];
        }

        return match (true) {
            $urun['price'] >= 2000 => 'efsanevi',
            $urun['price'] >= 1000 => 'destansi',
            $urun['price'] >= 500 => 'nadir',
            default => 'yaygin',
        };
    }

    /** Ürün şu an satışta mı? ('only_month' verilmemişse her zaman satıştadır) */
    public static function onSale(string $key): bool
    {
        $ay = self::ITEMS[$key]['only_month'] ?? null;

        return $ay === null || (int) now()->month === (int) $ay;
    }

    /** Sınırlı üründe kalan süre metni (sınırsızsa null). */
    public static function saleNote(string $key): ?string
    {
        $ay = self::ITEMS[$key]['only_month'] ?? null;

        if ($ay === null) {
            return null;
        }

        $adlar = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
            'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        if (! self::onSale($key)) {
            return 'Sadece '.$adlar[$ay].' ayında satışta';
        }

        $kalan = (int) ceil(now()->diffInDays(now()->endOfMonth(), true));

        return $kalan <= 1 ? 'Son gün!' : "Satışta — {$kalan} gün kaldı";
    }

    /** Ürünün gerektirdiği rozet koşulu (yoksa null). */
    public static function requirement(string $key): ?array
    {
        return self::ITEMS[$key]['requires'] ?? null;
    }
}
