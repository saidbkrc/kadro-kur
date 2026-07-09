<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nitelik onayı: bir üye, takım arkadaşının bir niteliğini onaylar (geri çekilebilir). */
class PlayerTraitEndorsement extends Model
{
    protected $fillable = ['player_id', 'trait_key', 'endorser_id'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function endorser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorser_id');
    }
}
