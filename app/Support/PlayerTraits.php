<?php

namespace App\Support;

/**
 * Nitelik kataloğu (LinkedIn tarzı sosyal onay) — rozetlerden farkı:
 * rozet veriden otomatik kazanılır, nitelik takım arkadaşları tarafından onaylanır.
 * Statik katalog; onaylar player_trait_endorsements tablosunda tutulur.
 */
class PlayerTraits
{
    /** Bir üyenin aynı oyuncuya onaylayabileceği en fazla nitelik sayısı. */
    public const MAX_PER_ENDORSER = 3;

    /** [key => [icon, name, desc, cat]] */
    public const ALL = [
        // ⚽ Hücum & Skor
        'keskin_nisanci' => ['icon' => '🏹', 'name' => 'Keskin Nişancı', 'desc' => 'Uzaktan ve isabetli şutlarıyla kaleciyi beklemediği anda avlar.', 'cat' => 'Hücum'],
        'sihirbaz' => ['icon' => '🪄', 'name' => 'Sihirbaz', 'desc' => 'Dar alanda beklenmedik çalımlar ve estetik hareketlerle adam eksiltir.', 'cat' => 'Hücum'],
        'ceza_sahasi_tilkisi' => ['icon' => '🦊', 'name' => 'Ceza Sahası Tilkisi', 'desc' => 'Topun nereye düşeceğini önceden sezer, en doğru yerde bitip golü koklar.', 'cat' => 'Hücum'],
        'ruzgarin_oglu' => ['icon' => '💨', 'name' => 'Rüzgarın Oğlu', 'desc' => 'İnanılmaz depar hızıyla defansın arkasına sarkar, kanatlardan uçar gider.', 'cat' => 'Hücum'],
        'fuze' => ['icon' => '🚀', 'name' => 'Füze', 'desc' => 'Mesafe tanımaksızın çıkardığı çok sert şutlarla kaleyi döver.', 'cat' => 'Hücum'],
        'duran_top_uzmani' => ['icon' => '🎯', 'name' => 'Duran Top Uzmanı', 'desc' => 'Frikik, korner, penaltı… duran top gördü mü gözü parlar.', 'cat' => 'Hücum'],

        // 🎼 Orta Saha & Oyun Kurucu
        'maestro' => ['icon' => '🎼', 'name' => 'Maestro', 'desc' => 'Takımın beynidir; oyunun temposunu ayarlar, akıl dolu paslarla hücumu yönlendirir.', 'cat' => 'Orta Saha'],
        'cigersiz' => ['icon' => '🏃', 'name' => 'Ciğersiz', 'desc' => 'Maç boyunca hiç durmadan koşar, sahanın her yerine basarak takıma enerji verir.', 'cat' => 'Orta Saha'],
        'asist_makinesi' => ['icon' => '🎁', 'name' => 'Asist Makinesi', 'desc' => 'Gol atmaktan çok attırmayı sever, adrese teslim kilit paslar çıkarır.', 'cat' => 'Orta Saha'],
        'buz_adam' => ['icon' => '🧊', 'name' => 'Buz Adam', 'desc' => 'Yoğun baskı altında bile panik yapmaz, top kaybetmeden soğukkanlı kalır.', 'cat' => 'Orta Saha'],
        'joker' => ['icon' => '🃏', 'name' => 'Joker', 'desc' => 'Defans, orta saha, forvet — nerede ihtiyaç varsa orada oynayan çok yönlü oyuncu.', 'cat' => 'Orta Saha'],

        // 🛡️ Savunma & Mücadele
        'beton' => ['icon' => '🗿', 'name' => 'Beton', 'desc' => 'Fiziksel gücü yüksek, ikili mücadelede ayakta kalan, geçit vermeyen stoper.', 'cat' => 'Savunma'],
        'golge' => ['icon' => '🕶️', 'name' => 'Gölge', 'desc' => 'Rakibin en tehlikeli oyuncusunu adım adım takip eder, nefes aldırmaz.', 'cat' => 'Savunma'],
        'gladyator' => ['icon' => '⚔️', 'name' => 'Gladyatör', 'desc' => 'Topu kazanmak için canını dişine takan, sert ve agresif savunmacı.', 'cat' => 'Savunma'],
        'orumcek' => ['icon' => '🕷️', 'name' => 'Örümcek', 'desc' => 'Uzun bacakları veya oyun zekasıyla araya girip rakibin pas trafiğini keser.', 'cat' => 'Savunma'],
        'hava_kurdu' => ['icon' => '🦅', 'name' => 'Hava Kurdu', 'desc' => 'İki ceza sahasında da kafa toplarının mutlak hakimi.', 'cat' => 'Savunma'],

        // 🧤 Kaleci & Özel Görev
        'panter' => ['icon' => '🐆', 'name' => 'Panter', 'desc' => 'Kalede inanılmaz refleksler — imkansız denilen şutları çıkarır.', 'cat' => 'Kaleci & Özel'],
        'miknatis' => ['icon' => '🧲', 'name' => 'Mıknatıs', 'desc' => 'Seken toplar hep onun önüne düşer; pozisyon alması kusursuz.', 'cat' => 'Kaleci & Özel'],
        'komutan' => ['icon' => '🪖', 'name' => 'Komutan', 'desc' => 'Takımı sürekli uyararak organize eden, sahanın sözlü ve mental lideri.', 'cat' => 'Kaleci & Özel'],
        'son_dakikaci' => ['icon' => '⏱️', 'name' => 'Son Dakikacı', 'desc' => 'Maçın en kritik son anlarında sahneye çıkıp sonucu değiştiren clutch oyuncu.', 'cat' => 'Kaleci & Özel'],
        'centilmen' => ['icon' => '🤝', 'name' => 'Centilmen', 'desc' => 'Gerginlikleri yatıştıran, faule başvurmadan temiz oynayan oyuncu.', 'cat' => 'Kaleci & Özel'],
        'moral_kupu' => ['icon' => '🔋', 'name' => 'Moral Küpü', 'desc' => 'Skor ne olursa olsun ortamı ayakta tutar — takımın neşesi.', 'cat' => 'Kaleci & Özel'],
        'dakik' => ['icon' => '🕐', 'name' => 'Dakik', 'desc' => 'Maça ilk gelen, asla ekmeyen — organizasyonun sigortası.', 'cat' => 'Kaleci & Özel'],
    ];

    /** Kategoriye göre gruplanmış katalog: [kategori => [key => tanım]]. */
    public static function grouped(): array
    {
        $out = [];

        foreach (self::ALL as $key => $trait) {
            $out[$trait['cat']][$key] = $trait;
        }

        return $out;
    }
}
