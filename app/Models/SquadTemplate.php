<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kayıtlı kadro şablonu. teams: [player_id => "A"|"B"]. Grup başına en fazla 3. */
class SquadTemplate extends Model
{
    /** Bir grupta tutulabilecek en fazla şablon sayısı. */
    public const MAX_PER_GROUP = 3;

    protected $fillable = ['group_id', 'name', 'teams'];

    protected function casts(): array
    {
        return [
            'teams' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
