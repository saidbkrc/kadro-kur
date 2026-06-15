<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eşleşme kuralı: iki oyuncu "ayrı takımlarda" (apart) veya "aynı takımda" (together). */
class Rule extends Model
{
    protected $fillable = ['group_id', 'player_a_id', 'player_b_id', 'type'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function playerA(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_a_id');
    }

    public function playerB(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_b_id');
    }
}
