<?php

namespace App\Livewire;

use App\Models\FootballMatch;
use App\Models\Goal;
use App\Models\MvpVote;
use App\Models\Rsvp;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $groupIds = $user->groups()->pluck('groups.id');
        $playerIds = $user->players()->pluck('id');

        $upcoming = FootballMatch::with('group')
            ->whereIn('group_id', $groupIds)
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->withCount(['rsvps as going_count' => fn ($q) => $q->where('status', 'going')->whereNull('waitlist_position')])
            ->get();

        $myRsvps = Rsvp::whereIn('player_id', $playerIds)
            ->whereIn('match_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('match_id');

        return view('livewire.dashboard', [
            'upcoming' => $upcoming,
            'myRsvps' => $myRsvps,
            'groups' => $user->groups()->withCount('members')->orderBy('name')->get(),
            'stats' => $this->personalStats($playerIds),
            'pending' => $this->pendingActions($user, $groupIds, $upcoming, $myRsvps),
        ]);
    }

    /** Kullanıcının tüm gruplardaki kişisel istatistikleri. */
    protected function personalStats($playerIds): array
    {
        $rsvps = Rsvp::whereIn('player_id', $playerIds)
            ->whereNotNull('team')
            ->whereHas('match', fn ($q) => $q->where('status', 'completed'))
            ->with('match:id,team_a_score,team_b_score')
            ->get();

        $wins = 0;
        foreach ($rsvps as $rsvp) {
            $match = $rsvp->match;
            if ($match->team_a_score === $match->team_b_score) {
                continue;
            }
            $winner = $match->team_a_score > $match->team_b_score ? 'A' : 'B';
            if ($rsvp->team === $winner) {
                $wins++;
            }
        }

        $played = $rsvps->count();

        return [
            'played' => $played,
            'wins' => $wins,
            'winRate' => $played > 0 ? round($wins / $played * 100) : 0,
            'goals' => (int) Goal::whereIn('player_id', $playerIds)->sum('count'),
            'mvpVotes' => MvpVote::whereIn('player_id', $playerIds)->count(),
        ];
    }

    /** Kullanıcının cevap/oy bekleyen işleri. */
    protected function pendingActions($user, $groupIds, $upcoming, $myRsvps): array
    {
        // RSVP bekleyen yaklaşan maçlar
        $needsRsvp = $upcoming->filter(fn ($m) => ! isset($myRsvps[$m->id]))->values();

        // Kadro oylaması bekleyen
        $squadVotes = FootballMatch::with('group')
            ->whereIn('group_id', $groupIds)
            ->where('squad_status', 'voting')
            ->get()
            ->filter(fn ($m) => in_array($user->id, $m->squadVoterIds(), true)
                && ! $m->squadVotes()->where('user_id', $user->id)->exists())
            ->values();

        // MVP oyu bekleyen (oylama açık, katılımcı, henüz oy yok)
        $mvpVotes = FootballMatch::with('group')
            ->whereIn('group_id', $groupIds)
            ->where('status', 'completed')
            ->where('mvp_closes_at', '>', now())
            ->get()
            ->filter(function ($m) use ($user) {
                $participants = $m->mainListRsvps()->pluck('player.user_id')->filter();

                return $participants->contains($user->id)
                    && ! $m->mvpVotes()->where('voter_id', $user->id)->exists();
            })
            ->values();

        return [
            'needsRsvp' => $needsRsvp,
            'squadVotes' => $squadVotes,
            'mvpVotes' => $mvpVotes,
            'total' => $needsRsvp->count() + $squadVotes->count() + $mvpVotes->count(),
        ];
    }
}
