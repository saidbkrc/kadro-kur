<?php

namespace App\Models;

use App\Services\TeamBalancer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class FootballMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'group_id', 'created_by', 'title', 'location', 'starts_at', 'capacity', 'status',
        'squad_status', 'formation_a', 'formation_b', 'pitch_layout',
        'team_a_score', 'team_b_score', 'mvp_closes_at', 'reminder_sent_at',
        'digest_sent_at', 'result_edited_at', 'result_edited_by', 'forma_goal_player_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'mvp_closes_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'digest_sent_at' => 'datetime',
            'result_edited_at' => 'datetime',
            'pitch_layout' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Skoru sonradan düzenleyen kişi (şeffaflık notu için). */
    public function resultEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'result_edited_by');
    }

    /** Forma golünü atan oyuncu (maç başına en fazla bir kişi; skora etki etmez). */
    public function formaGoalPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'forma_goal_player_id');
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class, 'match_id');
    }

    public function mvpVotes(): HasMany
    {
        return $this->hasMany(MvpVote::class, 'match_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'match_id');
    }

    public function squadVotes(): HasMany
    {
        return $this->hasMany(SquadVote::class, 'match_id');
    }

    public function performanceRatings(): HasMany
    {
        return $this->hasMany(MatchPerformanceRating::class, 'match_id');
    }

    /** Başkanın işaretlediği maç olayları (gerginlik, günün çalımı vb.). */
    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    /** Kehanet kuponları. */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }

    /** Asıl listedeki (yedek olmayan) "geliyorum" sayısı. */
    public function confirmedCount(): int
    {
        return $this->rsvps()->where('status', 'going')->whereNull('waitlist_position')->count();
    }

    public function isFull(): bool
    {
        return $this->confirmedCount() >= $this->capacity;
    }

    /** Maçı yöneten kişi: maçı açan veya grup sahibi/admini. */
    public function canManage(User $user): bool
    {
        return $user->id === $this->created_by || $this->group->isAdmin($user);
    }

    /** Asıl listedeki RSVP'ler (oyuncularıyla). */
    public function mainListRsvps(): EloquentCollection
    {
        return $this->rsvps()->with('player')
            ->where('status', 'going')
            ->whereNull('waitlist_position')
            ->orderBy('id')
            ->get();
    }

    /**
     * RSVP durumunu günceller; kapasite ve yedek listesi geçişlerini tek yerden yönetir.
     * Transaction + satır kilidi: iki kişi aynı anda son yeri kapamasın.
     * Asıl liste değişirse kurulmuş kadro ve oylaması sıfırlanır.
     */
    public function setRsvp(Player $player, string $status): Rsvp
    {
        return DB::transaction(function () use ($player, $status) {
            $this->rsvps()->lockForUpdate()->get();

            $mainBefore = $this->mainListPlayerIds();

            $rsvp = $this->rsvps()->firstOrNew(['player_id' => $player->id]);
            $previousStatus = $rsvp->exists ? $rsvp->status : null;
            $previousPosition = $rsvp->waitlist_position;

            if ($status === $previousStatus) {
                return $rsvp;
            }

            $rsvp->team = null;
            $rsvp->waitlist_position = $status === 'going' && $this->isFull()
                ? $this->nextWaitlistPosition()
                : null;
            $rsvp->status = $status;
            $rsvp->save();

            // "Geliyorum"dan vazgeçti: asıl listedeyse yedekten terfi ettir,
            // yedekteyse arkasındakilerin sırasını öne çek.
            if ($previousStatus === 'going') {
                $previousPosition === null
                    ? $this->promoteFirstWaitlisted()
                    : $this->closeWaitlistGap($previousPosition);
            }

            if ($mainBefore !== $this->mainListPlayerIds()) {
                if ($this->squad_status !== 'none') {
                    $this->resetSquad();
                }

                // Kadrodan çıkan oyuncular üzerine yapılmış kuponlar iade edilir
                app(\App\Services\KehanetService::class)->voidBetsForMissingPlayers($this);
            }

            return $rsvp;
        });
    }

    protected function mainListPlayerIds(): array
    {
        return $this->rsvps()
            ->where('status', 'going')
            ->whereNull('waitlist_position')
            ->orderBy('player_id')
            ->pluck('player_id')
            ->all();
    }

    protected function nextWaitlistPosition(): int
    {
        return (int) $this->rsvps()->where('status', 'going')->max('waitlist_position') + 1;
    }

    protected function promoteFirstWaitlisted(): void
    {
        $first = $this->rsvps()
            ->where('status', 'going')
            ->whereNotNull('waitlist_position')
            ->orderBy('waitlist_position')
            ->first();

        if ($first) {
            $vacated = $first->waitlist_position;
            $first->update(['waitlist_position' => null]);
            $this->closeWaitlistGap($vacated);
        }
    }

    protected function closeWaitlistGap(int $vacated): void
    {
        $this->rsvps()
            ->where('status', 'going')
            ->where('waitlist_position', '>', $vacated)
            ->decrement('waitlist_position');
    }

    /* ---------- kadro kurma + onay oylaması ---------- */

    /**
     * Asıl listedeki oyuncular için dengeli bölünme alternatiflerini hesaplar.
     *
     * @return list<array{score: float, a: list<int>, b: list<int>}>
     */
    public function balanceAlternatives(): array
    {
        $players = $this->mainListRsvps()
            ->map(fn (Rsvp $rsvp) => [
                'id' => $rsvp->player_id,
                'positions' => $rsvp->player->positions ?? [],
                'ovr' => $rsvp->player->load('attributeRatings')->displayRating(),
            ])
            ->values()
            ->all();

        $rules = $this->group->rules()
            ->get()
            ->map(fn (Rule $rule) => ['type' => $rule->type, 'a' => $rule->player_a_id, 'b' => $rule->player_b_id])
            ->all();

        return app(TeamBalancer::class)->balance($players, $rules);
    }

    /** Seçilen bölünmeyi uygular ve onay oylamasını (yeniden) başlatır. */
    public function applySquad(array $teamAIds, array $teamBIds): void
    {
        $wasVoting = $this->squad_status === 'voting'; // alternatif gezinirken tekrar bildirim gitmesin

        DB::transaction(function () use ($teamAIds, $teamBIds) {
            $this->rsvps()->update(['team' => null]);
            $this->rsvps()->whereIn('player_id', $teamAIds)->update(['team' => 'A']);
            $this->rsvps()->whereIn('player_id', $teamBIds)->update(['team' => 'B']);

            $this->squadVotes()->delete();
            $this->update(['squad_status' => 'voting', 'pitch_layout' => null]);
        });

        if (! $wasVoting) {
            app(\App\Services\PushNotifier::class)->squadVoteOpened($this, auth()->id());
        }
    }

    /** Kadroyu ve oylamayı sıfırlar (asıl liste değişince çağrılır). */
    public function resetSquad(): void
    {
        $this->rsvps()->update(['team' => null]);
        $this->squadVotes()->delete();
        $this->update(['squad_status' => 'none', 'pitch_layout' => null]);
    }

    /** Kadro oylamasında oy hakkı olan kullanıcı id'leri (asıl listedeki hesaplı oyuncular). */
    public function squadVoterIds(): array
    {
        return $this->mainListRsvps()
            ->pluck('player.user_id')
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{yes: int, no: int, eligible: int, needed: int} */
    public function squadVoteSummary(): array
    {
        $eligible = count($this->squadVoterIds());
        $votes = $this->squadVotes()->get();
        $percent = Setting::int('squad_approval_percent', 60);

        return [
            'yes' => $votes->where('approve', true)->count(),
            'no' => $votes->where('approve', false)->count(),
            'eligible' => $eligible,
            'needed' => (int) ceil($eligible * $percent / 100), // varsayılan %60 çoğunluk
        ];
    }

    /** Oy kullanır; %60 evet sağlanırsa kadroyu kesinleştirir. */
    public function castSquadVote(User $user, bool $approve): void
    {
        if ($this->squad_status !== 'voting' || ! in_array($user->id, $this->squadVoterIds(), true)) {
            return;
        }

        $this->squadVotes()->updateOrCreate(['user_id' => $user->id], ['approve' => $approve]);

        $summary = $this->squadVoteSummary();
        if ($summary['yes'] >= $summary['needed']) {
            $this->update(['squad_status' => 'approved']);
        }
    }

    /* ---------- maç sonu ---------- */

    /** MVP oylama penceresi süresi (saat) — varsayılan 1 hafta (bir sonraki maça kadar). 0 = sınırsız. */
    public static function ratingWindowHours(): int
    {
        return Setting::int('rating_window_hours', 168);
    }

    /** Pencere sınırsız mı (panelden 0 yapıldıysa — test için)? */
    public static function ratingUnlimited(): bool
    {
        return self::ratingWindowHours() === 0;
    }

    /** MVP oylaması açık mı? Sınırsız modda tamamlanmış her maç açıktır (test). */
    public function mvpOpen(): bool
    {
        if (self::ratingUnlimited()) {
            return $this->status === 'completed';
        }

        return $this->mvp_closes_at !== null && now()->lt($this->mvp_closes_at);
    }

    /** Performans penceresi süresi (saat) — varsayılan 1 hafta (bir sonraki maça kadar). */
    public static function performanceWindowHours(): int
    {
        return Setting::int('performance_window_hours', 168);
    }

    /** Performans puanlamasının kapanacağı an (maç saatinden itibaren). */
    public function perfClosesAt(): ?\Illuminate\Support\Carbon
    {
        return $this->starts_at?->copy()->addHours(self::performanceWindowHours());
    }

    /** Maç sonu performans puanlaması açık mı? MVP'den bağımsız, daha uzun pencere. */
    public function perfOpen(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        if (self::ratingUnlimited()) {
            return true;
        }

        return $this->perfClosesAt() !== null && now()->lt($this->perfClosesAt());
    }
}
