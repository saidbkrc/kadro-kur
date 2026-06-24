<?php

namespace App\Models;

use App\Support\Attributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grup içi oyuncu kimliği. user_id null ise "misafir" — başkanın isimle eklediği,
 * henüz hesabı olmayan oyuncu. Kişi kayıt olunca eşleştirilir, geçmişi korunur.
 */
class Player extends Model
{
    /** Ortalama puanın görünür olması için gereken en az oylama sayısı (varsayılan; panelden değişebilir). */
    public const MIN_RATINGS_FOR_VISIBILITY = 5;

    /** Misafir oyuncu (hesapsız) puanlanmaz; sabit varsayılan puanla gelir. */
    public const GUEST_RATING = 6.5;

    protected $fillable = ['group_id', 'user_id', 'name', 'shirt_number', 'positions', 'foot'];

    /** Panel ayarından okunur, yoksa varsayılan sabite düşer. */
    public static function minRatingsForVisibility(): int
    {
        return Setting::int('min_ratings_visibility', self::MIN_RATINGS_FOR_VISIBILITY);
    }

    protected function casts(): array
    {
        return [
            'positions' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attributeRatings(): HasMany
    {
        return $this->hasMany(AttributeRating::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function mvpVotes(): HasMany
    {
        return $this->hasMany(MvpVote::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function isGoalkeeper(): bool
    {
        return in_array('KL', $this->positions ?? [], true);
    }

    /** Tercih edilen ayağın kısa rozeti: sağ→R, sol→L, çift→R/L. */
    public function footBadge(): string
    {
        return match ($this->foot) {
            'left' => 'L',
            'both' => 'R/L',
            default => 'R',
        };
    }

    /** Bu oyuncunun puanlanacağı özellikler: [key => etiket]. */
    public function ratableAttributes(): array
    {
        return Attributes::forPositions($this->positions ?? []);
    }

    /** Tüm puanlayıcıların ortalaması: [key => ort]. Hiç puan yoksa boş döner. */
    public function averageAttributes(): array
    {
        $allScores = $this->attributeRatings->pluck('scores');

        if ($allScores->isEmpty()) {
            return [];
        }

        $averages = [];

        foreach (array_keys($this->ratableAttributes()) as $key) {
            $values = $allScores->pluck($key)->filter(fn ($v) => is_numeric($v));

            if ($values->isNotEmpty()) {
                $averages[$key] = round($values->avg(), 2);
            }
        }

        return $averages;
    }

    /** Genel puan (OVR) — puanlanmamış özellikler 5 (orta) kabul edilir. Misafir: sabit 6.5. */
    public function overall(): float
    {
        if ($this->isGuest()) {
            return self::GUEST_RATING;
        }

        return round(Attributes::overall($this->averageAttributes(), $this->positions ?? []), 1);
    }

    /** Son 5 maçın performans ortalaması (her maçın kendi oy ortalamalarının ortalaması). Yoksa null. Misafir: yok. */
    public function matchPerformance(): ?float
    {
        if ($this->isGuest()) {
            return null;
        }

        $perMatch = MatchPerformanceRating::query()
            ->where('match_performance_ratings.player_id', $this->id)
            ->join('matches', 'matches.id', '=', 'match_performance_ratings.match_id')
            ->groupBy('match_performance_ratings.match_id', 'matches.starts_at')
            ->orderByDesc('matches.starts_at')
            ->limit(5)
            ->selectRaw('AVG(match_performance_ratings.score) as avg_score')
            ->pluck('avg_score');

        return $perMatch->isEmpty() ? null : round($perMatch->avg(), 2);
    }

    /** FC26 tarzı nihai puan: OVR×0.8 + son 5 maç performansı×0.2. Performans yoksa sadece OVR. */
    public function displayRating(): float
    {
        $ovr = $this->overall();
        $perf = $this->matchPerformance();

        return $perf === null ? $ovr : round($ovr * 0.8 + $perf * 0.2, 1);
    }

    /** Form göstergesi: nihai puan − OVR (▲ artı / ▼ eksi). Performans yoksa null. */
    public function formDelta(): ?float
    {
        if ($this->matchPerformance() === null) {
            return null;
        }

        return round($this->displayRating() - $this->overall(), 1);
    }

    public function ratingCount(): int
    {
        return $this->attributeRatings->count();
    }

    /** Ortalama puan herkese (oyuncunun kendisine de) ancak eşik oylama sayısından sonra gösterilir. Misafir: sabit 6.5 hep görünür. */
    public function overallIsPublic(): bool
    {
        if ($this->isGuest()) {
            return true;
        }

        return $this->ratingCount() >= self::minRatingsForVisibility();
    }
}
