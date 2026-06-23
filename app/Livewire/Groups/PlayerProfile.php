<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\Player;
use App\Services\PlayerBadges;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Oyuncu profili: kazanılan/kilitli rozetler + sezon istatistik özeti. */
#[Layout('layouts.app')]
class PlayerProfile extends Component
{
    public Group $group;

    public Player $player;

    public function mount(Group $group, Player $player): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        // İzolasyon: oyuncuyu yetkili gruptan ilişki üzerinden çek (başka grubun id'si → 404)
        $this->group = $group;
        $this->player = $group->players()->findOrFail($player->id);
    }

    public function render(PlayerBadges $badges): View
    {
        $this->player->load('attributeRatings');

        $stats = $badges->statsForPlayer($this->player);

        return view('livewire.groups.player-profile', [
            'stats' => $stats,
            'badges' => $badges->evaluate($stats),
        ]);
    }
}
