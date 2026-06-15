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

    protected $fillable = ['group_id', 'user_id', 'name', 'shirt_number', 'positions'];

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

    /** Genel puan (OVR) — puanlanmamış özellikler 5 (orta) kabul edilir. */
    public function overall(): float
    {
        return round(Attributes::overall($this->averageAttributes(), $this->positions ?? []), 1);
    }

    public function ratingCount(): int
    {
        return $this->attributeRatings->count();
    }

    /** Ortalama puan herkese (oyuncunun kendisine de) ancak eşik oylama sayısından sonra gösterilir. */
    public function overallIsPublic(): bool
    {
        return $this->ratingCount() >= self::minRatingsForVisibility();
    }
}
