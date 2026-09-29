<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Services\SeasonVoting;
use App\Support\Season;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Sezon sonu oylaması: oylama açıkken oy verme, kapandıktan sonra sonuçlar. */
#[Layout('layouts.app')]
class SeasonVote extends Component
{
    public Group $group;

    public ?string $notice = null;

    public function mount(Group $group): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        $this->group = $group;
    }

    public function vote(string $category, int $playerId): void
    {
        $sezon = Season::votingNow();

        if ($sezon === null) {
            $this->notice = 'Şu an açık bir sezon oylaması yok.';

            return;
        }

        // Aday doğrulaması (grup + sezon aday listesi, kendine oy yok) serviste
        $this->notice = app(SeasonVoting::class)
            ->vote(Auth::user(), $this->group, $sezon, $category, $playerId)['message'];
    }

    public function render(SeasonVoting $oylama): View
    {
        $user = Auth::user();
        $acik = Season::votingNow();

        // Sonuçları gösterilecek son kapanmış sezon (sezon sistemi sonrası, oyu olan)
        $sonuclanan = collect(Season::pastForGroup($this->group))
            ->first(fn (Season $s) => $s->key() >= Season::LAUNCH_KEY
                && $s->isVotingClosed()
                && $oylama->results($this->group, $s) !== []);

        $benimOyuncum = $this->group->playerFor($user);

        return view('livewire.groups.season-vote', [
            'open' => $acik,
            'canVote' => $acik ? $oylama->canVote($user, $this->group, $acik) : false,
            'candidates' => $acik ? $oylama->candidates($this->group, $acik) : collect(),
            'myVotes' => $acik ? $oylama->myVotes($user, $this->group, $acik) : [],
            'voterCount' => $acik ? $oylama->voterCount($this->group, $acik) : 0,
            'myPlayerId' => $benimOyuncum?->id,
            'resultsSeason' => $sonuclanan,
            'results' => $sonuclanan ? $oylama->results($this->group, $sonuclanan) : [],
            'nextOpens' => Season::current()->end()->copy()->addSecond(),
            'categories' => SeasonVoting::CATEGORIES,
        ]);
    }
}
