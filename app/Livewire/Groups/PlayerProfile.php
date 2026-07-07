<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\Player;
use App\Services\PlayerBadges;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Oyuncu profili: FIFA kartı + rozetler + sezon özeti + kafa kafaya karşılaştırma. */
#[Layout('layouts.app')]
class PlayerProfile extends Component
{
    use WithFileUploads;

    public Group $group;

    public Player $player;

    /** Karşılaştırılan oyuncu (?kiyas=ID ile paylaşılabilir). */
    #[Url(as: 'kiyas')]
    public ?int $compareId = null;

    /** Kart fotoğrafı yüklemesi (sadece oyuncunun kendisi). */
    public $photo = null;

    public function mount(Group $group, Player $player): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        // İzolasyon: oyuncuyu yetkili gruptan ilişki üzerinden çek (başka grubun id'si → 404)
        $this->group = $group;
        $this->player = $group->players()->findOrFail($player->id);
    }

    /** Fotoğraf seçilir seçilmez yüklenir — yalnızca kendi kartı. */
    public function updatedPhoto(): void
    {
        abort_unless($this->player->user_id === Auth::id(), 403);

        $this->validate(
            ['photo' => 'image|max:2048'],
            ['photo.image' => 'Geçerli bir görsel seç (jpg/png/webp).', 'photo.max' => 'Görsel en fazla 2 MB olabilir.'],
        );

        if ($this->player->photo_path) {
            Storage::disk('public')->delete($this->player->photo_path);
        }

        // Kare kırp + 512px'e küçült (telefon fotoğrafları dev gelmesin)
        $path = 'oyuncu-foto/'.uniqid().'.jpg';
        Storage::disk('public')->put($path, \App\Support\SquareImage::make($this->photo));

        $this->player->update(['photo_path' => $path]);
        $this->photo = null;
        $this->player->refresh();
    }

    public function render(PlayerBadges $badges): View
    {
        $this->player->load('attributeRatings');

        $groupStats = $badges->statsForGroup($this->group);
        $stats = $groupStats->get($this->player->id, PlayerBadges::emptyStats());

        // Kafa kafaya: rakip de aynı gruptan ilişki üzerinden (çapraz grup → 404)
        $compare = null;
        $compareStats = null;
        if ($this->compareId) {
            $compare = $this->group->players()->findOrFail($this->compareId);

            if ($compare->id === $this->player->id) {
                $compare = null;
                $this->compareId = null;
            } else {
                $compare->load('attributeRatings');
                $compareStats = $groupStats->get($compare->id, PlayerBadges::emptyStats());
            }
        }

        $earnedCount = fn (array $s) => collect($badges->evaluate($s))->where('earned', true)->count();

        return view('livewire.groups.player-profile', [
            'stats' => $stats,
            'badges' => $badges->evaluate($stats),
            'otherPlayers' => $this->group->players()->whereKeyNot($this->player->id)->orderBy('name')->get(),
            'compare' => $compare,
            'compareStats' => $compareStats,
            'myEarned' => $earnedCount($stats),
            'compareEarned' => $compareStats !== null ? $earnedCount($compareStats) : null,
            'bestPartner' => app(\App\Services\TeamChemistry::class)->bestPartnerFor($this->player->id, $this->group),
        ]);
    }
}
