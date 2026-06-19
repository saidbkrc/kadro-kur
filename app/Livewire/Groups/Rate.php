<?php

namespace App\Livewire\Groups;

use App\Models\AttributeRating;
use App\Models\Group;
use App\Models\Player;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Anonim akran puanlaması: özellik özellik 1-10 slider. Kimin ne verdiği gösterilmez. */
#[Layout('layouts.app')]
class Rate extends Component
{
    public Group $group;

    public ?int $selectedId = null;

    /** @var array<string, int> [özellik => puan] */
    public array $scores = [];

    public bool $saved = false;

    public function mount(Group $group): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        $this->group = $group;

        // "Puanla" butonundan gelindiyse oyuncuyu hazır seç (?oyuncu=ID)
        $playerId = (int) request()->query('oyuncu');
        if ($playerId > 0) {
            $valid = $group->players()
                ->whereKey($playerId)
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', Auth::id()))
                ->exists();

            if ($valid) {
                $this->select($playerId);
            }
        }
    }

    public function select(int $playerId): void
    {
        $player = $this->group->players()->findOrFail($playerId);
        abort_if($player->user_id === Auth::id(), 403); // kendine puan yok

        $existing = AttributeRating::where('player_id', $playerId)
            ->where('rater_id', Auth::id())
            ->first();

        $defaults = array_fill_keys(array_keys($player->ratableAttributes()), 5);

        $this->selectedId = $playerId;
        $this->scores = array_replace($defaults, array_intersect_key($existing->scores ?? [], $defaults));
        $this->saved = false;
    }

    /** +/- butonu: özelliği 1 artır/azalt (1-10 arası). Mobilde slider yerine kolay. */
    public function adjust(string $key, int $delta): void
    {
        if (! array_key_exists($key, $this->scores)) {
            return;
        }

        $this->scores[$key] = max(1, min(10, (int) $this->scores[$key] + $delta));
    }

    public function save(): void
    {
        $player = $this->group->players()->findOrFail($this->selectedId);
        abort_if($player->user_id === Auth::id(), 403);

        $clean = [];
        foreach (array_keys($player->ratableAttributes()) as $key) {
            $clean[$key] = max(1, min(10, (int) ($this->scores[$key] ?? 5)));
        }

        AttributeRating::updateOrCreate(
            ['player_id' => $player->id, 'rater_id' => Auth::id()],
            ['scores' => $clean],
        );

        $this->saved = true;
    }

    public function render(): View
    {
        $players = $this->group->players()
            ->with('attributeRatings')
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', Auth::id()))
            ->orderBy('name')
            ->get();

        $myRatings = AttributeRating::whereIn('player_id', $players->pluck('id'))
            ->where('rater_id', Auth::id())
            ->pluck('player_id');

        return view('livewire.groups.rate', [
            'players' => $players,
            'ratedIds' => $myRatings,
            'selected' => $this->selectedId
                ? $players->firstWhere('id', $this->selectedId)
                : null,
        ]);
    }
}
