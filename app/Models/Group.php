<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $fillable = [
        'owner_id', 'name', 'description', 'invite_code',
        'match_day', 'match_time', 'default_location', 'capacity', 'auto_schedule',
    ];

    protected function casts(): array
    {
        return [
            'auto_schedule' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            $group->invite_code ??= Str::upper(Str::random(8));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FootballMatch::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function isMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** Grup sahibi veya admin rolündeki üye: maç/kural/misafir yönetir, sonuç girer. */
    public function isAdmin(User $user): bool
    {
        return $user->id === $this->owner_id
            || $this->members()->whereKey($user->id)->wherePivotIn('role', ['owner', 'admin'])->exists();
    }

    /** Üyenin bu gruptaki oyuncu kaydı. */
    public function playerFor(User $user): ?Player
    {
        return $this->players()->firstWhere('user_id', $user->id);
    }

    /** Üyenin oyuncu kaydını getirir, yoksa oluşturur (gruba katılırken çağrılır). */
    public function ensurePlayerFor(User $user): Player
    {
        return $this->players()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => Str::limit($user->name, 24, ''),
                'positions' => ['OS'],
            ],
        );
    }
}
