<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bir üyenin bir oyuncuya verdiği özellik puanları. Puanlayan kimliği asla gösterilmez. */
class AttributeRating extends Model
{
    protected $fillable = ['player_id', 'rater_id', 'scores'];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }
}
