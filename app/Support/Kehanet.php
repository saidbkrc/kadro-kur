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

    public const MAX_ODDS = 20.0;

    /** Kombine kuponda en az / en çok bacak sayısı. */
    public const MIN_LEGS = 2;

    public const MAX_LEGS = 5;

    /**
     * Maç başarı ödülleri (Çim). Kupondan bağımsız — sahadaki performansın karşılığı.
     * MVP oylaması kapanınca tek seferde dağıtılır; misafir oyuncular (hesapsız) alamaz.
     */
    public const BONUS = [
        'top_scorer' => ['amount' => 100, 'icon' => '⚽', 'name' => 'En çok gol atan'],
        'mvp' => ['amount' => 50, 'icon' => '🏆', 'name' => 'Maçın adamı'],
        'forma' => ['amount' => 25, 'icon' => '👕', 'name' => 'Forma golü'],
    ];

    /** Başkanın maç sonrası işaretlediği olaylar — manuel market'lerin kaynağı. */
    public const EVENTS = [
        'gerginlik' => ['icon' => '😤', 'name' => 'Gerginlik yaşayan', 'hint' => 'Saha içinde en çok tartışan'],
        'calim' => ['icon' => '🪄', 'name' => 'Günün çalımı', 'hint' => 'En güzel çalımı atan'],
        'iska' => ['icon' => '🤦', 'name' => 'Günün ıskası', 'hint' => 'Kaçırılmaz pozisyonu kaçıran'],
        'kurtaris' => ['icon' => '🧤', 'name' => 'Günün kurtarışı', 'hint' => 'En iyi kurtarışı yapan'],
        'gec_gelen' => ['icon' => '⏰', 'name' => 'En geç gelen', 'hint' => 'Maça en son yetişen'],
    ];

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
        'gerginlik' => ['icon' => '😤', 'name' => 'Gerginlik yaşayacak', 'kind' => 'oyuncu', 'source' => 'event'],
        'calim' => ['icon' => '🪄', 'name' => 'Günün çalımı', 'kind' => 'oyuncu', 'source' => 'event'],
        'iska' => ['icon' => '🤦', 'name' => 'Günün ıskası', 'kind' => 'oyuncu', 'source' => 'event'],
        'kurtaris' => ['icon' => '🧤', 'name' => 'Günün kurtarışı', 'kind' => 'oyuncu', 'source' => 'event'],
        'gec_gelen' => ['icon' => '⏰', 'name' => 'En geç gelen', 'kind' => 'oyuncu', 'source' => 'event'],
    ];

    /** Takım seçenekleri market'e göre değişir. */
    public static function teamOptions(string $market): array
    {
        return $market === 'clean_sheet'
            ? ['A' => 'Turuncu', 'B' => 'Yeşil', 'N' => 'İkisi de yer']
            : ['A' => 'Turuncu', 'X' => 'Beraberlik', 'B' => 'Yeşil'];
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
