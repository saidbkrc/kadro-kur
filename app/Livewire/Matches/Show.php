<?php

namespace App\Livewire\Matches;

use App\Models\FootballMatch;
use App\Models\MvpVote;
use App\Models\Player;
use App\Models\Rsvp;
use App\Services\MatchScheduler;
use App\Support\Attributes;
use App\Support\PitchLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public FootballMatch $match;

    /** Dengeleme alternatifleri (buton tıklanınca hesaplanır, oturumda gezdirilir) */
    public array $alternatives = [];

    public int $altIndex = 0;

    /** Elle takas: ilk tıklanan oyuncu */
    public ?int $swapArmed = null;

    /** Başkanın katılım yönetimi paneli açık mı */
    public bool $showManageRsvp = false;

    // Sonuç formu
    public bool $showResultForm = false;

    public ?int $teamAScore = null;

    public ?int $teamBScore = null;

    /** Golcüler: [player_id => gol] */
    public array $goals = [];

    public function mount(FootballMatch $match): void
    {
        abort_unless($match->group->isMember(Auth::user()), 403);

        $this->match = $match;
        $this->teamAScore = $match->team_a_score;
        $this->teamBScore = $match->team_b_score;
        $this->goals = $match->goals()->pluck('count', 'player_id')->all();
    }

    /* ---------- RSVP ---------- */

    public function rsvp(string $status): void
    {
        abort_unless(in_array($status, ['going', 'not_going', 'maybe'], true), 400);

        if ($this->match->status !== 'scheduled') {
            return;
        }

        $player = $this->match->group->ensurePlayerFor(Auth::user());
        $this->match->setRsvp($player, $status);
        $this->match->refresh();
    }

    /** Başkan, gruptaki herhangi bir oyuncunun (misafir veya kayıtlı üye) katılımını işaretler. */
    public function setPlayerRsvp(int $playerId, string $status): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);
        abort_unless(in_array($status, ['going', 'not_going', 'maybe'], true), 400);

        if ($this->match->status !== 'scheduled') {
            return;
        }

        $player = $this->match->group->players()->findOrFail($playerId);
        $this->match->setRsvp($player, $status);
        $this->match->refresh();
    }

    /* ---------- kadro kurma + oylama ---------- */

    public function buildSquads(): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);

        try {
            $this->alternatives = $this->match->balanceAlternatives();
        } catch (InvalidArgumentException $e) {
            $this->addError('squad', $e->getMessage());

            return;
        }

        $this->altIndex = 0;
        $this->applyCurrentAlternative();
    }

    public function nextAlternative(): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);

        if (count($this->alternatives) < 2) {
            return;
        }

        $this->altIndex = ($this->altIndex + 1) % count($this->alternatives);
        $this->applyCurrentAlternative();
    }

    protected function applyCurrentAlternative(): void
    {
        $alt = $this->alternatives[$this->altIndex];
        $this->match->applySquad($alt['a'], $alt['b']);
        $this->swapArmed = null;
        $this->match->refresh();
    }

    /** Elle takas: bir takımdan oyuncuya tıkla, sonra diğer takımdan birine — yer değiştirirler. */
    public function swap(int $playerId): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);

        $clicked = $this->match->rsvps()->where('player_id', $playerId)->whereNotNull('team')->first();
        if (! $clicked) {
            return;
        }

        if ($this->swapArmed === null) {
            $this->swapArmed = $playerId;

            return;
        }

        if ($this->swapArmed === $playerId) {
            $this->swapArmed = null;

            return;
        }

        $armed = $this->match->rsvps()->where('player_id', $this->swapArmed)->whereNotNull('team')->first();

        if (! $armed || $armed->team === $clicked->team) {
            $this->swapArmed = $playerId; // aynı takım → seçimi taşı

            return;
        }

        [$armedTeam, $clickedTeam] = [$armed->team, $clicked->team];
        $armed->update(['team' => $clickedTeam]);
        $clicked->update(['team' => $armedTeam]);

        // Takas sonrası elle verilen konumlar geçersiz, oylama da yeniden başlar
        $layout = $this->match->pitch_layout ?? [];
        unset($layout[$armed->player_id], $layout[$clicked->player_id]);
        unset($layout[(string) $armed->player_id], $layout[(string) $clicked->player_id]);

        $this->match->squadVotes()->delete();
        $this->match->update(['squad_status' => 'voting', 'pitch_layout' => $layout ?: null]);

        $this->swapArmed = null;
        $this->match->refresh();
    }

    public function voteSquad(bool $approve): void
    {
        $this->match->castSquadVote(Auth::user(), $approve);
        $this->match->refresh();
    }

    /* ---------- diziliş + saha ---------- */

    public function setFormation(string $side, string $value): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);
        abort_unless(in_array($side, ['a', 'b'], true), 400);

        $formation = in_array($value, Attributes::FORMATIONS, true) ? $value : null;

        // O takımın elle verilen konumları sıfırlanır
        $teamIds = $this->match->rsvps()->where('team', strtoupper($side))->pluck('player_id');
        $layout = $this->match->pitch_layout ?? [];
        foreach ($teamIds as $id) {
            unset($layout[$id], $layout[(string) $id]);
        }

        $this->match->update(["formation_{$side}" => $formation, 'pitch_layout' => $layout ?: null]);
        $this->match->refresh();
    }

    public function movePlayer(int $playerId, float $x, float $y): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);
        abort_unless($this->match->rsvps()->where('player_id', $playerId)->whereNotNull('team')->exists(), 404);

        $layout = $this->match->pitch_layout ?? [];
        $layout[$playerId] = [
            'x' => max(30, min(PitchLayout::W - 30, round($x, 1))),
            'y' => max(30, min(PitchLayout::H - 32, round($y, 1))),
        ];

        $this->match->update(['pitch_layout' => $layout]);
        $this->match->refresh();
    }

    public function resetLayout(): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);

        $this->match->update(['pitch_layout' => null]);
        $this->match->refresh();
    }

    /* ---------- maç sonu ---------- */

    public function saveResult(): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);

        $this->validate(
            [
                'teamAScore' => 'required|integer|min:0|max:99',
                'teamBScore' => 'required|integer|min:0|max:99',
                'goals.*' => 'nullable|integer|min:0|max:30',
            ],
            [
                'teamAScore.required' => 'Turuncu takımın skoru zorunlu.',
                'teamBScore.required' => 'Yeşil takımın skoru zorunlu.',
            ],
        );

        $this->match->update([
            'team_a_score' => $this->teamAScore,
            'team_b_score' => $this->teamBScore,
            'status' => 'completed',
            // MVP oylaması skor girilince 24 saat açılır (tekrar kaydetmek süreyi uzatmaz)
            'mvp_closes_at' => $this->match->mvp_closes_at ?? now()->addDay(),
        ]);

        $participantIds = $this->match->mainListRsvps()->pluck('player_id');

        $this->match->goals()->delete();
        foreach ($this->goals as $playerId => $count) {
            if ((int) $count > 0 && $participantIds->contains((int) $playerId)) {
                $this->match->goals()->create(['player_id' => $playerId, 'count' => (int) $count]);
            }
        }

        // Haftalık otomatik maç: sıradaki maçı hemen aç
        app(MatchScheduler::class)->ensureUpcomingMatch($this->match->group);

        $this->showResultForm = false;
        $this->match->refresh();
    }

    /** MVP oyu: 24 saat içinde, 1 oy, değiştirilemez, kendine oy yok. */
    public function voteMvp(int $playerId): void
    {
        abort_unless($this->match->mvpOpen(), 403);

        $participants = $this->match->mainListRsvps();
        $myPlayer = $participants->pluck('player')->firstWhere('user_id', Auth::id());

        abort_if($myPlayer === null, 403); // sadece kadrodaki hesaplı oyuncular
        abort_if($myPlayer->id === $playerId, 403);
        abort_unless($participants->pluck('player_id')->contains($playerId), 403);

        MvpVote::firstOrCreate(
            ['match_id' => $this->match->id, 'voter_id' => Auth::id()],
            ['player_id' => $playerId],
        );
    }

    public function cancelMatch(): void
    {
        abort_unless($this->match->canManage(Auth::user()), 403);

        $this->match->update(['status' => 'cancelled']);
    }

    /* ---------- render ---------- */

    public function render(): View
    {
        $rsvps = $this->match->rsvps()->with('player.attributeRatings')->get();

        $going = $rsvps->filter(fn (Rsvp $r) => $r->status === 'going' && $r->waitlist_position === null)
            ->sortBy('id')->values();
        $waitlist = $rsvps->filter(fn (Rsvp $r) => $r->status === 'going' && $r->waitlist_position !== null)
            ->sortBy('waitlist_position')->values();

        $teamA = $going->where('team', 'A')->values();
        $teamB = $going->where('team', 'B')->values();

        $myPlayer = $this->match->group->playerFor(Auth::user());
        $myRsvp = $myPlayer ? $rsvps->firstWhere('player_id', $myPlayer->id) : null;

        $avg = fn ($team) => $team->isEmpty()
            ? 0.0
            : round($team->avg(fn (Rsvp $r) => $r->player->overall()), 1);

        // Başkanın katılım yönetimi: tüm kadro + her oyuncunun mevcut durumu
        $roster = $this->match->group->players()->orderBy('name')->get();
        $rsvpByPlayer = $rsvps->keyBy('player_id');

        $myVote = $this->match->mvpVotes()->where('voter_id', Auth::id())->first();

        return view('livewire.matches.show', [
            'group' => $this->match->group,
            'going' => $going,
            'waitlist' => $waitlist,
            'maybe' => $rsvps->where('status', 'maybe')->values(),
            'notGoing' => $rsvps->where('status', 'not_going')->values(),
            'roster' => $roster,
            'rsvpByPlayer' => $rsvpByPlayer,
            'myPlayer' => $myPlayer,
            'myRsvp' => $myRsvp,
            'teamA' => $teamA,
            'teamB' => $teamB,
            'avgA' => $avg($teamA),
            'avgB' => $avg($teamB),
            'pitchA' => PitchLayout::layout($this->pitchData($teamA), 'A', $this->match->formation_a, $this->match->pitch_layout ?? []),
            'pitchB' => PitchLayout::layout($this->pitchData($teamB), 'B', $this->match->formation_b, $this->match->pitch_layout ?? []),
            'voteSummary' => $this->match->squad_status !== 'none' ? $this->match->squadVoteSummary() : null,
            'mySquadVote' => $this->match->squadVotes()->where('user_id', Auth::id())->first(),
            'canVoteSquad' => in_array(Auth::id(), $this->match->squadVoterIds(), true),
            'canManage' => $this->match->canManage(Auth::user()),
            'isParticipant' => $myPlayer !== null && $going->pluck('player_id')->contains($myPlayer->id),
            'myMvpVote' => $myVote,
            'mvpResults' => $this->match->mvpVotes()
                ->selectRaw('player_id, COUNT(*) as votes')
                ->groupBy('player_id')
                ->orderByDesc('votes')
                ->with('player')
                ->get(),
            'matchGoals' => $this->match->goals()->with('player')->orderByDesc('count')->get(),
        ]);
    }

    /** PitchLayout için oyuncu verisi. */
    protected function pitchData($teamRsvps): array
    {
        return $teamRsvps->map(fn (Rsvp $r) => [
            'id' => $r->player_id,
            'name' => $r->player->name,
            'number' => $r->player->shirt_number,
            'positions' => $r->player->positions ?? [],
            'ovr' => $r->player->overall(),
            'ovr_public' => $r->player->overallIsPublic(),
            'attrs' => $r->player->averageAttributes(),
        ])->values()->all();
    }
}
