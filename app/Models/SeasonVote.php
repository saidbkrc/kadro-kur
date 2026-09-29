<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sezon sonu oylamasında bir kişinin bir kategorideki oyu (anonim — yalnızca toplam gösterilir). */
class SeasonVote extends Model
{
    protected $fillable = ['group_id', 'season', 'category', 'voter_id', 'player_id'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
