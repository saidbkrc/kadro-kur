<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kehanet kuponu — sanal "Çim" ile oynanır, gerçek para değildir. */
class Prediction extends Model
{
    protected $fillable = [
        'user_id', 'match_id', 'market_key', 'selection',
        'odds', 'stake', 'status', 'payout', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'odds' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    /** Kupon tutarsa ödenecek toplam (yatırılan dahil). */
    public function potentialPayout(): int
    {
        return (int) round($this->stake * (float) $this->odds);
    }
}
