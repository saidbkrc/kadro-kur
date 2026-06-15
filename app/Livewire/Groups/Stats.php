<?php

namespace App\Livewire\Groups;

use App\Models\FootballMatch;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Grup istatistikleri: oyuncu tablosu, gol krallığı, maç geçmişi. */
#[Layout('layouts.app')]
class Stats extends Component
{
    public Group $group;

    public function mount(Group $group): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        $this->group = $group;
    }

    public function render(): View
    {
        $matches = $this->group->matches()
            ->where('status', 'completed')
            ->with(['rsvps.player', 'goals.player', 'mvpVotes.player'])
            ->orderByDesc('starts_at')
            ->get();

        $stats = [];
        $touch = function ($player) use (&$stats) {
            if ($player === null) {
                return null;
            }

            return $stats[$player->id] ??= [
                'player' => $player,
                'played' => 0, 'win' => 0, 'draw' => 0, 'loss' => 0,
                'goals' => 0, 'mvp' => 0,
            ];
        };

        foreach ($matches as $match) {
            $isDraw = $match->team_a_score === $match->team_b_score;
            $winner = $match->team_a_score > $match->team_b_score ? 'A' : 'B';

            foreach ($match->rsvps as $rsvp) {
                if ($rsvp->status !== 'going' || $rsvp->waitlist_position !== null) {
                    continue;
                }

                $entry = $touch($rsvp->player);
                if ($entry === null) {
                    continue;
                }

                $stats[$rsvp->player_id]['played']++;

                if ($rsvp->team !== null) {
                    if ($isDraw) {
                        $stats[$rsvp->player_id]['draw']++;
                    } elseif ($rsvp->team === $winner) {
                        $stats[$rsvp->player_id]['win']++;
                    } else {
                        $stats[$rsvp->player_id]['loss']++;
                    }
                }
            }

            foreach ($match->goals as $goal) {
                if ($touch($goal->player) !== null) {
                    $stats[$goal->player_id]['goals'] += $goal->count;
                }
            }

            // Maçın MVP'si: en çok oyu alan(lar) — oylama kapanmışsa sayılır
            if (! $match->mvpOpen() && $match->mvpVotes->isNotEmpty()) {
                $counts = $match->mvpVotes->countBy('player_id');
                $max = $counts->max();

                foreach ($counts->filter(fn ($c) => $c === $max)->keys() as $playerId) {
                    $vote = $match->mvpVotes->firstWhere('player_id', $playerId);
                    if ($touch($vote?->player) !== null) {
                        $stats[$playerId]['mvp']++;
                    }
                }
            }
        }

        $playerStats = collect($stats)
            ->sortByDesc(fn (array $s) => [$s['played'] > 0 ? $s['win'] / $s['played'] : 0, $s['played']])
            ->values();

        $topScorers = collect($stats)
            ->filter(fn (array $s) => $s['goals'] > 0)
            ->sortBy([['goals', 'desc'], ['played', 'asc']])
            ->values();

        return view('livewire.groups.stats', [
            'matches' => $matches,
            'playerStats' => $playerStats,
            'topScorers' => $topScorers,
        ]);
    }
}
