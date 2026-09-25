<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mağazadan satın alınmış kozmetik ürün. */
class CimPurchase extends Model
{
    protected $fillable = ['user_id', 'item_key', 'price', 'gifted_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Hediye edildiyse gönderen (satın alındıysa null). */
    public function gifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gifted_by');
    }
}
