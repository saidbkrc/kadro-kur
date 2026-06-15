<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kadro onay oyu: kadrodaki hesaplı oyuncular oylar, %60 evet → kadro kesinleşir. */
class SquadVote extends Model
{
    protected $fillable = ['match_id', 'user_id', 'approve'];

    protected function casts(): array
    {
        return [
            'approve' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
