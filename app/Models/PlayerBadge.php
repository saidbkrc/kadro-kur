<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kazanılmış rozet kaydı — yeni kazanım tespiti + bildirim için (hesap yine türetilmiş). */
class PlayerBadge extends Model
{
    protected $fillable = ['player_id', 'badge_key'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
