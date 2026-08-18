<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kombine kupon: birden çok tahmin tek kuponda, oranlar çarpılır — hepsi tutmalı. */
class PredictionSlip extends Model
{
    protected $fillable = ['user_id', 'group_id', 'stake', 'total_odds', 'status', 'payout', 'settled_at'];

    protected function casts(): array
    {
        return ['total_odds' => 'decimal:2', 'settled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(Prediction::class, 'slip_id');
    }

    public function potentialPayout(): int
    {
        return (int) round($this->stake * (float) $this->total_odds);
    }
}
