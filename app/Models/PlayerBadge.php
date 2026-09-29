<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kazanılmış rozet kaydı — yeni kazanım tespiti + bildirim için (hesap yine türetilmiş). */
class PlayerBadge extends Model
{
    /** season: "2026-09" gibi sezon anahtarı; NULL = sezon sistemi öncesi kayıt. */
    protected $fillable = ['player_id', 'badge_key', 'season'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
