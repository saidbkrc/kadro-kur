<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maç sonu performans puanı (1-10). Anonim; kimin kime verdiği gösterilmez. */
class MatchPerformanceRating extends Model
{
    protected $fillable = ['match_id', 'rater_id', 'player_id', 'score'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
