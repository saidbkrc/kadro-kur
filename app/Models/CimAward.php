<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verilmiş Çim ödülü kaydı — aynı ödülün tekrar verilmesini engeller. */
class CimAward extends Model
{
    protected $fillable = ['user_id', 'group_id', 'award_key', 'ref', 'amount'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
