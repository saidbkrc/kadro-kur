<?php

namespace App\Livewire\Groups;

use App\Models\FootballMatch;
use App\Models\Group;
use App\Services\PlayerBadges;
use App\Services\TeamChemistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Support\Season;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Grup istatistikleri: oyuncu tablosu, gol krallığı, maç geçmişi. */
#[Layout('layouts.app')]
class Stats extends Component
{
    public Group $group;

    /** Oyuncu arama kutusu */
    public string $search = '';

    /** Maç geçmişinde gösterilen maç sayısı ("daha fazla" ile artar) */
    public int $matchLimit = 10;

    /** Seçili sezon anahtarı ("2026-09"), "tum" = tüm zamanlar, boş = içinde bulunulan sezon. */
    #[Url(as: 'sezon', except: '')]
    public string $sezon = '';

    public function updatedSezon(): void
    {
        $this->matchLimit = 10;
    }

    public function loadMoreMatches(): void
    {
        $this->matchLimit += 10;
    }

    public function mount(Group $group): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        $this->group = $group;
    }

    public function render(PlayerBadges $badges): View
    {
        // Seçili sezon: null = tüm zamanlar; geçersiz/boş anahtar içinde bulunulan sezona düşer
        $secili = $this->sezon === 'tum'
            ? null
            : (Season::fromKey($this->sezon) ?? Season::current());

        // Her oyuncunun kazandığı rozet ikonları (oyuncu tablosunda gösterilir) — seçili sezonun
        $earnedIcons = $badges->statsForGroup($this->group, $secili)->map(
            fn (array $s) => collect($badges->evaluate($s))->where('earned', true)->pluck('icon')->all()
        );

        $matches = $this->group->matches()
            ->where('status', 'completed')
            ->when($secili, fn ($q) => $q->whereBetween('starts_at', [$secili->start, $secili->end()]))
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

        // Oyuncu arama: ada göre filtrele (Türkçe karakter duyarsız değil ama küçük/büyük duyarsız)
        $ara = trim($this->search);
        $filtrele = fn ($liste) => $ara === ''
            ? $liste
            : $liste->filter(fn (array $s) => mb_stripos($s['player']->name, $ara) !== false)->values();

        // Bitmiş sezonun şampiyonları (eşitlikte hepsi) — sezon devam ederken "şu an önde"
        $lider = function (string $alan) use ($stats) {
            $zirve = collect($stats)->max($alan);

            return $zirve > 0
                ? ['value' => $zirve, 'players' => collect($stats)->where($alan, $zirve)->pluck('player')->values()]
                : null;
        };

        // Sezon seçenekleri: bu sezon + maçı olan geçmiş sezonlar
        $macOlanSezonlar = $this->group->matches()->where('status', 'completed')->pluck('starts_at')
            ->map(fn ($t) => Season::forDate($t)->key())->unique()->flip();
        $sezonlar = collect([Season::current(), ...Season::pastForGroup($this->group)])
            ->filter(fn (Season $s) => $s->isCurrent() || $macOlanSezonlar->has($s->key()))
            ->values();

        return view('livewire.groups.stats', [
            'season' => $secili,
            'seasons' => $sezonlar,
            'leaders' => $secili === null ? null : [
                'goals' => $lider('goals'),
                'mvp' => $lider('mvp'),
                'played' => $lider('played'),
            ],
            'matches' => $matches->take($this->matchLimit),
            'totalMatches' => $matches->count(),
            'playerStats' => $filtrele($playerStats),
            'topScorers' => $filtrele($topScorers),
            'earnedIcons' => $earnedIcons,
            'chemistry' => app(TeamChemistry::class)->pairsForGroup($this->group)->take(5),
        ]);
    }
}
