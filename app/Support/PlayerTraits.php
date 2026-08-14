<?php

namespace App\Support;

/**
 * Nitelik kataloğu (LinkedIn tarzı sosyal onay) — rozetlerden farkı:
 * rozet veriden otomatik kazanılır, nitelik takım arkadaşları tarafından onaylanır.
 *
 * İki tür var: 'positive' (güçlü yönler) ve 'negative' (şakacı takılmalar).
 * Negatifler daha sıkı kurallara tabidir: onay limiti düşük, en az MIN_NEGATIVE_VISIBLE
 * onaya ulaşmadan kimseye görünmez (tek kişi yapıştıramaz) ve bildirim gönderilmez.
 */
class PlayerTraits
{
    /** Bir üyenin aynı oyuncuya onaylayabileceği en fazla OLUMLU nitelik sayısı. */
    public const MAX_PER_ENDORSER = 3;

    /** Aynı oyuncuya onaylanabilecek en fazla OLUMSUZ nitelik sayısı (daha kısıtlı). */
    public const MAX_NEGATIVE_PER_ENDORSER = 2;

    /** Olumsuz nitelik profilde görünmeye başlaması için gereken onay sayısı. */
    public const MIN_NEGATIVE_VISIBLE = 3;

    /** [key => [icon, name, desc, cat, type]] */
    public const ALL = [
        // ⚽ Hücum & Skor
        'keskin_nisanci' => ['icon' => '🏹', 'name' => 'Keskin Nişancı', 'desc' => 'Uzaktan ve isabetli şutlarıyla kaleciyi beklemediği anda avlar.', 'cat' => 'Hücum', 'type' => 'positive'],
        'sihirbaz' => ['icon' => '🪄', 'name' => 'Sihirbaz', 'desc' => 'Dar alanda beklenmedik çalımlar ve estetik hareketlerle adam eksiltir.', 'cat' => 'Hücum', 'type' => 'positive'],
        'ceza_sahasi_tilkisi' => ['icon' => '🦊', 'name' => 'Ceza Sahası Tilkisi', 'desc' => 'Topun nereye düşeceğini önceden sezer, en doğru yerde bitip golü koklar.', 'cat' => 'Hücum', 'type' => 'positive'],
        'ruzgarin_oglu' => ['icon' => '💨', 'name' => 'Rüzgarın Oğlu', 'desc' => 'İnanılmaz depar hızıyla defansın arkasına sarkar, kanatlardan uçar gider.', 'cat' => 'Hücum', 'type' => 'positive'],
        'fuze' => ['icon' => '🚀', 'name' => 'Füze', 'desc' => 'Mesafe tanımaksızın çıkardığı çok sert şutlarla kaleyi döver.', 'cat' => 'Hücum', 'type' => 'positive'],
        'duran_top_uzmani' => ['icon' => '🎯', 'name' => 'Duran Top Uzmanı', 'desc' => 'Frikik, korner, penaltı… duran top gördü mü gözü parlar.', 'cat' => 'Hücum', 'type' => 'positive'],

        // 🎼 Orta Saha & Oyun Kurucu
        'maestro' => ['icon' => '🎼', 'name' => 'Maestro', 'desc' => 'Takımın beynidir; oyunun temposunu ayarlar, akıl dolu paslarla hücumu yönlendirir.', 'cat' => 'Orta Saha', 'type' => 'positive'],
        'cigersiz' => ['icon' => '🏃', 'name' => 'Ciğersiz', 'desc' => 'Maç boyunca hiç durmadan koşar, sahanın her yerine basarak takıma enerji verir.', 'cat' => 'Orta Saha', 'type' => 'positive'],
        'asist_makinesi' => ['icon' => '🎁', 'name' => 'Asist Makinesi', 'desc' => 'Gol atmaktan çok attırmayı sever, adrese teslim kilit paslar çıkarır.', 'cat' => 'Orta Saha', 'type' => 'positive'],
        'buz_adam' => ['icon' => '🧊', 'name' => 'Buz Adam', 'desc' => 'Yoğun baskı altında bile panik yapmaz, top kaybetmeden soğukkanlı kalır.', 'cat' => 'Orta Saha', 'type' => 'positive'],
        'joker' => ['icon' => '🃏', 'name' => 'Joker', 'desc' => 'Defans, orta saha, forvet — nerede ihtiyaç varsa orada oynayan çok yönlü oyuncu.', 'cat' => 'Orta Saha', 'type' => 'positive'],

        // 🛡️ Savunma & Mücadele
        'beton' => ['icon' => '🗿', 'name' => 'Beton', 'desc' => 'Fiziksel gücü yüksek, ikili mücadelede ayakta kalan, geçit vermeyen stoper.', 'cat' => 'Savunma', 'type' => 'positive'],
        'golge' => ['icon' => '🕶️', 'name' => 'Gölge', 'desc' => 'Rakibin en tehlikeli oyuncusunu adım adım takip eder, nefes aldırmaz.', 'cat' => 'Savunma', 'type' => 'positive'],
        'gladyator' => ['icon' => '⚔️', 'name' => 'Gladyatör', 'desc' => 'Topu kazanmak için canını dişine takan, sert ve agresif savunmacı.', 'cat' => 'Savunma', 'type' => 'positive'],
        'orumcek' => ['icon' => '🕷️', 'name' => 'Örümcek', 'desc' => 'Uzun bacakları veya oyun zekasıyla araya girip rakibin pas trafiğini keser.', 'cat' => 'Savunma', 'type' => 'positive'],
        'hava_kurdu' => ['icon' => '🦅', 'name' => 'Hava Kurdu', 'desc' => 'İki ceza sahasında da kafa toplarının mutlak hakimi.', 'cat' => 'Savunma', 'type' => 'positive'],

        // 🧤 Kaleci & Özel Görev
        'panter' => ['icon' => '🐆', 'name' => 'Panter', 'desc' => 'Kalede inanılmaz refleksler — imkansız denilen şutları çıkarır.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],
        'miknatis' => ['icon' => '🧲', 'name' => 'Mıknatıs', 'desc' => 'Seken toplar hep onun önüne düşer; pozisyon alması kusursuz.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],
        'komutan' => ['icon' => '🪖', 'name' => 'Komutan', 'desc' => 'Takımı sürekli uyararak organize eden, sahanın sözlü ve mental lideri.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],
        'son_dakikaci' => ['icon' => '⏱️', 'name' => 'Son Dakikacı', 'desc' => 'Maçın en kritik son anlarında sahneye çıkıp sonucu değiştiren clutch oyuncu.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],
        'centilmen' => ['icon' => '🤝', 'name' => 'Centilmen', 'desc' => 'Gerginlikleri yatıştıran, faule başvurmadan temiz oynayan oyuncu.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],
        'moral_kupu' => ['icon' => '🔋', 'name' => 'Moral Küpü', 'desc' => 'Skor ne olursa olsun ortamı ayakta tutar — takımın neşesi.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],
        'dakik' => ['icon' => '🕐', 'name' => 'Dakik', 'desc' => 'Maça ilk gelen, asla ekmeyen — organizasyonun sigortası.', 'cat' => 'Kaleci & Özel', 'type' => 'positive'],

        // 😅 Şakacı takılmalar (olumsuz)
        'kazma' => ['icon' => '🪓', 'name' => 'Kazma', 'desc' => 'Topla ilişkisi inişli çıkışlı; bazen top ona, bazen o topa çarpar.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'uydu_vurucu' => ['icon' => '🛰️', 'name' => 'Uydu Vurucu', 'desc' => 'Şutları kaleyi değil, komşu sahayı ve gökyüzünü bulur.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'cabuk_biten' => ['icon' => '🪫', 'name' => 'Çabuk Biten', 'desc' => 'İlk 10 dakika roket, kalanı sakin bir yürüyüş turu.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'top_cimrisi' => ['icon' => '🔒', 'name' => 'Top Cimrisi', 'desc' => 'Topu aldı mı bırakmaz; "pas" kelimesi sözlüğünde yok.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'faul_makinesi' => ['icon' => '🚨', 'name' => 'Faul Makinesi', 'desc' => 'Sert girer, sonra gayet ciddi bir yüzle "top oynadım" der.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'saha_hocasi' => ['icon' => '🗣️', 'name' => 'Saha Hocası', 'desc' => 'Kendi pek koşmaz ama herkese pozisyon ve taktik dağıtır.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'agir_abi' => ['icon' => '🐌', 'name' => 'Ağır Abi', 'desc' => 'Deparı bir olay, koşusu efsane — acelesi hiç yok.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'gosteri_meraklisi' => ['icon' => '🎪', 'name' => 'Gösteri Meraklısı', 'desc' => 'Basit pas dururken çalım denemesi şart; bazen tutar, çoğunlukla tutmaz.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'cam_adam' => ['icon' => '🚑', 'name' => 'Cam Adam', 'desc' => 'Her maç yeni bir sakatlık hikâyesiyle döner.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'gec_gelen' => ['icon' => '⏰', 'name' => 'Geç Gelen', 'desc' => 'Maç başladı, o hâlâ "5 dakikaya oradayım" diyor.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'baruthane' => ['icon' => '😤', 'name' => 'Baruthane', 'desc' => 'Saha içi tartışmalar onsuz başlamaz, onunla biter.', 'cat' => 'Takılmalar', 'type' => 'negative'],
        'kaybolan' => ['icon' => '👻', 'name' => 'Kaybolan', 'desc' => 'Kadroda var, sahada var ama nerede olduğu bir muamma.', 'cat' => 'Takılmalar', 'type' => 'negative'],
    ];

    /** Nitelik olumsuz mu? */
    public static function isNegative(string $key): bool
    {
        return (self::ALL[$key]['type'] ?? 'positive') === 'negative';
    }

    /** Bu türde bir üyenin verebileceği en fazla onay sayısı. */
    public static function limitFor(string $type): int
    {
        return $type === 'negative' ? self::MAX_NEGATIVE_PER_ENDORSER : self::MAX_PER_ENDORSER;
    }

    /** Türe göre kategori kategori gruplanmış katalog: [kategori => [key => tanım]]. */
    public static function grouped(string $type = 'positive'): array
    {
        $out = [];

        foreach (self::ALL as $key => $trait) {
            if (($trait['type'] ?? 'positive') === $type) {
                $out[$trait['cat']][$key] = $trait;
            }
        }

        return $out;
    }
}
