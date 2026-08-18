<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Çim hareket kaydı (sanal bakiye geçmişi). */
class CimTransaction extends Model
{
    protected $fillable = ['user_id', 'amount', 'type', 'description', 'balance_after'];

    public const LABELS = [
        'grant' => '🌱 Haftalık yükleme',
        'bet' => '🎫 Kupon',
        'win' => '🎉 Kazanç',
        'refund' => '↩ İade',
        'bonus' => '🎁 Maç ödülü',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
