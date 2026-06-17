<?php

namespace App\Livewire\Profile;

use App\Models\Goal;
use App\Models\MvpVote;
use App\Models\Player;
use App\Models\Rsvp;
use App\Support\Attributes;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Profil sayfasındaki "Saha Profilim": kişisel istatistik özeti +
 * kullanıcının her grubundaki kendi oyuncu kaydını (pozisyon/ayak/forma no) düzenlemesi.
 */
class FieldProfile extends Component
{
    public ?int $editingPlayerId = null;

    /** @var list<string> tıklama sırası = öncelik */
    public array $posOrder = [];

    public string $editFoot = 'right';

    public ?int $editNumber = null;

    public function edit(int $playerId): void
    {
        $player = $this->ownPlayer($playerId);

        $this->editingPlayerId = $player->id;
        $this->posOrder = $player->positions ?? [];
        $this->editFoot = $player->foot ?? 'right';
        $this->editNumber = $player->shirt_number;
        $this->resetErrorBag();
    }

    public function togglePosition(string $code): void
    {
        if (! array_key_exists($code, Attributes::POSITIONS)) {
            return;
        }

        if (in_array($code, $this->posOrder, true)) {
            $this->posOrder = array_values(array_filter($this->posOrder, fn ($p) => $p !== $code));
        } else {
            $this->posOrder[] = $code;
        }
    }

    public function save(): void
    {
        $player = $this->ownPlayer($this->editingPlayerId);

        if ($this->posOrder === []) {
            $this->addError('posOrder', 'En az bir pozisyon seçmelisin.');

            return;
        }

        $this->validate([
            'editNumber' => 'nullable|integer|min:1|max:99',
            'editFoot' => 'required|in:'.implode(',', array_keys(Attributes::FEET)),
        ]);

        $player->update([
            'positions' => $this->posOrder,
            'foot' => $this->editFoot,
            'shirt_number' => $this->editNumber,
        ]);

        $this->reset('editingPlayerId', 'posOrder', 'editFoot', 'editNumber');
    }

    public function cancel(): void
    {
        $this->reset('editingPlayerId', 'posOrder', 'editFoot', 'editNumber');
        $this->resetErrorBag();
    }

    /** Yalnızca kullanıcının kendi oyuncu kaydı (güvenlik). */
    protected function ownPlayer(int $playerId): Player
    {
        $player = Auth::user()->players()->find($playerId);

        abort_unless($player, 404);

        return $player;
    }

    public function render()
    {
        $players = Auth::user()->players()->with('group', 'attributeRatings')->get();
        $playerIds = $players->pluck('id');

        return view('livewire.profile.field-profile', [
            'players' => $players->sortBy(fn (Player $p) => $p->group->name)->values(),
            'stats' => $this->stats($playerIds),
        ]);
    }

    /** Tüm gruplardaki kişisel istatistikler. */
    protected function stats($playerIds): array
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
            if ($rsvp->team === ($match->team_a_score > $match->team_b_score ? 'A' : 'B')) {
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
}
