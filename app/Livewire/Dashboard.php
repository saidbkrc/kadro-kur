<?php

namespace App\Livewire;

use App\Models\FootballMatch;
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

        $upcoming = FootballMatch::with('group')
            ->whereIn('group_id', $user->groups()->pluck('groups.id'))
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->withCount(['rsvps as going_count' => fn ($q) => $q->where('status', 'going')->whereNull('waitlist_position')])
            ->get();

        $myRsvps = Rsvp::whereIn('player_id', $user->players()->pluck('id'))
            ->whereIn('match_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('match_id');

        return view('livewire.dashboard', [
            'upcoming' => $upcoming,
            'myRsvps' => $myRsvps,
            'groupCount' => $user->groups()->count(),
        ]);
    }
}
