<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Başkanın maç sonrası işaretlediği olay (gerginlik, günün çalımı vb.). */
class MatchEvent extends Model
{
    protected $fillable = ['match_id', 'event_key', 'player_id'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
