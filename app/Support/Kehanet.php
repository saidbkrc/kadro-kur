<?php

namespace App\Support;

/**
 * Kehanet: eğlence amaçlı tahmin oyunu sabitleri.
 * "Çim" tamamen sanal bir puandır — gerçek para değildir, çevrilemez, satılamaz.
 */
class Kehanet
{
    /** Haftalık otomatik yükleme miktarı. */
    public const WEEKLY_GRANT = 100;

    /** Yeni kullanıcının başlangıç bakiyesi. */
    public const STARTING_BALANCE = 100;

    /** Tek kupona yatırılabilecek en az / en çok Çim. */
    public const MIN_STAKE = 5;

    public const MAX_STAKE = 500;

    /** Oran sınırları (aşırı uçları kırpar). */
    public const MIN_ODDS = 1.05;

    /** Genel tavan — skor tam tahmini gibi doğası gereği uzak ihtimaller için. */
    public const MAX_ODDS = 20.0;

    /** Oyuncu bazlı market'lerde tavan daha düşük (kadro büyüdükçe oran şişmesin). */
    public const MAX_ODDS_PLAYER = 10.0;

    /** Market'in oran tavanı. */
    public static function maxOdds(string $market): float
    {
        return (self::MARKETS[$market]['kind'] ?? '') === 'oyuncu'
            ? self::MAX_ODDS_PLAYER
            : self::MAX_ODDS;
    }

    /** Kombine kuponda en az / en çok bacak sayısı. */
    public const MIN_LEGS = 2;

    public const MAX_LEGS = 5;

    /**
     * Kombine sınırları. Oranlar çarpıldığı için tek kombine mağazanın
     * tamamını alabiliyordu (500 × 500× = 250.000) — hem tutar hem oran kırpılır.
     * En yüksek kombine kazancı: 100 × 50× = 5.000 Çim.
     */
    public const MAX_PARLAY_STAKE = 100;

    public const MAX_PARLAY_ODDS = 50.0;

    /**
     * Bir kullanıcının tek maça yatırabileceği toplam Çim (tekli + o maçı
     * içeren kombineler). Bakiyeyi tek maçta eritmeyi engeller.
     */
    public const MAX_MATCH_STAKE = 1000;

    /** Başkanın maç sonrası işaretlediği olaylar — manuel market'lerin kaynağı. */
    public const EVENTS = [
        'macin_golu' => ['icon' => '🌟', 'name' => 'Maçın golü', 'hint' => 'Maçın en güzel golünü atan'],
        'absurt_gol' => ['icon' => '🤪', 'name' => 'En absürt gol', 'hint' => 'Nasıl girdiği belli olmayan golü atan'],
        'asist' => ['icon' => '🎁', 'name' => 'Günün asisti', 'hint' => 'En güzel gol pasını veren'],
        'gerginlik' => ['icon' => '😤', 'name' => 'Gerginlik yaşayan', 'hint' => 'Saha içinde en çok tartışan'],
        'calim' => ['icon' => '🪄', 'name' => 'Günün çalımı', 'hint' => 'En güzel çalımı atan'],
        'iska' => ['icon' => '🤦', 'name' => 'Günün ıskası', 'hint' => 'Kaçırılmaz pozisyonu kaçıran'],
        'kurtaris' => ['icon' => '🧤', 'name' => 'Günün kurtarışı', 'hint' => 'En iyi kurtarışı yapan'],
        'gec_gelen' => ['icon' => '⏰', 'name' => 'En geç gelen', 'hint' => 'Maça en son yetişen'],
    ];

    /** İşaretlenmemiş olay kuponları bu kadar gün sonra iade edilir (sonsuza kadar beklemesin). */
    public const EVENT_VOID_AFTER_DAYS = 14;

    /**
     * Tahmin market'leri. 'auto' olanlar maç verisinden kendiliğinden sonuçlanır;
     * 'event' olanlar başkanın işaretlediği maç olayından çözülür.
     *
     * kind: takim | oyuncu | altust
     */
    public const MARKETS = [
        'winner' => ['icon' => '🏆', 'name' => 'Maç sonucu', 'kind' => 'takim', 'source' => 'auto'],
        'exact_score' => ['icon' => '🎯', 'name' => 'Skor tam tahmini', 'kind' => 'skor', 'source' => 'auto'],
        'total_goals' => ['icon' => '🎯', 'name' => 'Toplam gol', 'kind' => 'altust', 'source' => 'auto'],
        'clean_sheet' => ['icon' => '🛡️', 'name' => 'Gol yemeyen takım', 'kind' => 'takim', 'source' => 'auto'],
        'scorer' => ['icon' => '⚽', 'name' => 'Gol atacak oyuncu', 'kind' => 'oyuncu', 'source' => 'auto'],
        'brace' => ['icon' => '⚡', 'name' => '2+ gol atacak oyuncu', 'kind' => 'oyuncu', 'source' => 'auto'],
        'mvp' => ['icon' => '🌟', 'name' => 'Maçın adamı', 'kind' => 'oyuncu', 'source' => 'auto'],
        'forma' => ['icon' => '👕', 'name' => 'Forma golünü atacak', 'kind' => 'oyuncu', 'source' => 'auto'],
        'top_perf' => ['icon' => '📈', 'name' => 'En yüksek performans', 'kind' => 'oyuncu', 'source' => 'auto'],
        'macin_golu' => ['icon' => '🌟', 'name' => 'Maçın golünü atacak', 'kind' => 'oyuncu', 'source' => 'event'],
        'absurt_gol' => ['icon' => '🤪', 'name' => 'En absürt golü atacak', 'kind' => 'oyuncu', 'source' => 'event'],
        'asist' => ['icon' => '🎁', 'name' => 'Günün asistini yapacak', 'kind' => 'oyuncu', 'source' => 'event'],
        // no_self: kişi kendini seçemez — sonucu bilerek kendisi yaratabileceği olaylar
        'gerginlik' => ['icon' => '😤', 'name' => 'Gerginlik yaşayacak', 'kind' => 'oyuncu', 'source' => 'event', 'no_self' => true],
        'calim' => ['icon' => '🪄', 'name' => 'Günün çalımı', 'kind' => 'oyuncu', 'source' => 'event'],
        'iska' => ['icon' => '🤦', 'name' => 'Günün ıskası', 'kind' => 'oyuncu', 'source' => 'event'],
        // prior: veri yokken olasılık kadroya eşit değil, pozisyona göre bölünür (bkz. OddsCalculator)
        'kurtaris' => ['icon' => '🧤', 'name' => 'Günün kurtarışı', 'kind' => 'oyuncu', 'source' => 'event', 'prior' => 'kaleci'],
        'gec_gelen' => ['icon' => '⏰', 'name' => 'En geç gelen', 'kind' => 'oyuncu', 'source' => 'event', 'no_self' => true],
    ];

    /** Takım seçenekleri market'e göre değişir. */
    public static function teamOptions(string $market): array
    {
        return $market === 'clean_sheet'
            ? ['A' => 'Turuncu', 'B' => 'Yeşil', 'N' => 'İkisi de yer']
            : ['A' => 'Turuncu', 'X' => 'Beraberlik', 'B' => 'Yeşil'];
    }

    /** Oyuncu kendi adına bu market'te tahmin yapabilir mi? */
    public static function allowsSelf(string $market): bool
    {
        return empty(self::MARKETS[$market]['no_self']);
    }

    public static function label(string $market): string
    {
        return self::MARKETS[$market]['name'] ?? $market;
    }

    public static function icon(string $market): string
    {
        return self::MARKETS[$market]['icon'] ?? '🎲';
    }
}
