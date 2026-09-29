<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Sabit takvimli sezon. Tablo yok — sezon, tarihten türetilir: Eylül'den başlayarak
 * 3'er aylık dönemler (Eyl–Kas, Ara–Şub, Mar–May, Haz–Ağu). Sezon anahtarı başlangıç
 * ayıdır ("2026-09"). Süreyi değiştirmek için LENGTH_MONTHS / START_MONTH yeterli;
 * geçmiş veri yeni dönemlere göre kendiliğinden yeniden bölünür.
 */
final class Season
{
    public const START_MONTH = 9;

    public const LENGTH_MONTHS = 3;

    /**
     * Sezon sisteminin açıldığı sezon. Bundan önce bitmiş sezonlar için sezon sonu
     * ödülü dağıtılmaz; rozetlerde bu sezon eski (sezonsuz) kayıtları sahiplenir ki
     * yayına alınınca zaten kazanılmış rozetler için tekrar bildirim/Çim gitmesin.
     */
    public const LAUNCH_KEY = '2026-09';

    /** Başlangıç ayına göre sezon adı (START_MONTH/LENGTH değişirse genel ada düşer). */
    protected const NAMES = [9 => 'Sonbahar', 12 => 'Kış', 3 => 'İlkbahar', 6 => 'Yaz'];

    private function __construct(public readonly Carbon $start)
    {
    }

    public static function current(): self
    {
        return self::forDate(now());
    }

    public static function forDate(\DateTimeInterface $tarih): self
    {
        $t = Carbon::instance($tarih);

        // Mutlak ay sayısı, sezon çıpasına göre kaydırılıp dönem uzunluğuna bölünür
        $ay = $t->year * 12 + ($t->month - 1) - (self::START_MONTH - 1);
        $baslangic = (int) (floor($ay / self::LENGTH_MONTHS) * self::LENGTH_MONTHS) + (self::START_MONTH - 1);

        return new self(Carbon::create(intdiv($baslangic, 12), $baslangic % 12 + 1, 1)->startOfDay());
    }

    /** "2026-09" gibi anahtardan; geçersizse null. */
    public static function fromKey(?string $key): ?self
    {
        if ($key === null || ! preg_match('/^\d{4}-\d{2}$/', $key)) {
            return null;
        }

        $sezon = self::forDate(Carbon::createFromFormat('Y-m-d', $key.'-01'));

        return $sezon->key() === $key ? $sezon : null;   // dönem başı olmayan ay reddedilir
    }

    /**
     * Grubun ilk tamamlanmış maçından bu yana bitmiş sezonlar (yeniden eskiye).
     * Maçı hiç olmayan ara sezonlar da listede kalır; gösterirken boşlar elenebilir.
     *
     * @return list<self>
     */
    public static function pastForGroup(\App\Models\Group $group): array
    {
        $ilk = $group->matches()->where('status', 'completed')->min('starts_at');

        if ($ilk === null) {
            return [];
        }

        $ilkSezon = self::forDate(Carbon::parse($ilk));
        $liste = [];

        for ($s = self::current()->previous(); $s->start->gte($ilkSezon->start); $s = $s->previous()) {
            $liste[] = $s;
        }

        return $liste;
    }

    public function key(): string
    {
        return $this->start->format('Y-m');
    }

    public function end(): Carbon
    {
        return $this->start->copy()->addMonthsNoOverflow(self::LENGTH_MONTHS)->subSecond();
    }

    public function previous(): self
    {
        return self::forDate($this->start->copy()->subDay());
    }

    public function next(): self
    {
        return self::forDate($this->end()->copy()->addSecond());
    }

    public function contains(\DateTimeInterface $tarih): bool
    {
        return Carbon::instance($tarih)->between($this->start, $this->end());
    }

    public function isCurrent(): bool
    {
        return $this->key() === self::current()->key();
    }

    public function isLaunch(): bool
    {
        return $this->key() === self::LAUNCH_KEY;
    }

    /** "Sonbahar 2026", kış gibi yıl sınırını aşanlarda "Kış 2026/27". */
    public function name(): string
    {
        $ad = self::NAMES[$this->start->month] ?? null;

        if ($ad === null) {
            return 'Sezon '.$this->start->translatedFormat('F Y');
        }

        $bitis = $this->end();

        return $bitis->year !== $this->start->year
            ? $ad.' '.$this->start->year.'/'.$bitis->format('y')
            : $ad.' '.$this->start->year;
    }

    /** Sezon sonu oylaması: sezon bitince bu kadar gün açık kalır. */
    public const VOTING_DAYS = 7;

    /** Sezon sonu oylamasının kapandığı an (bitişten VOTING_DAYS gün sonra, gün sonu). */
    public function votingClosesAt(): Carbon
    {
        return $this->end()->copy()->addDays(self::VOTING_DAYS);
    }

    public function isVotingOpen(): bool
    {
        return now()->gt($this->end()) && now()->lte($this->votingClosesAt());
    }

    public function isVotingClosed(): bool
    {
        return now()->gt($this->votingClosesAt());
    }

    /** Şu an oylaması açık olan sezon (yeni sezonun ilk günleri), yoksa null. */
    public static function votingNow(): ?self
    {
        $onceki = self::current()->previous();

        return $onceki->isVotingOpen() ? $onceki : null;
    }

    /** Sezon bitene kadar kalan gün (bitmişse 0). */
    public function daysLeft(): int
    {
        return max(0, (int) ceil(now()->diffInDays($this->end(), false)));
    }
}
