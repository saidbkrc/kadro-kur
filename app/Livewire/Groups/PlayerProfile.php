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

    /** Nitelik onaylama paneli açık mı + geri bildirim mesajı */
    public bool $showTraitPicker = false;

    public ?string $traitNotice = null;

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

    /** Panelde biriktirilen seçim — "Kaydet" basılana dek DB'ye yazılmaz. */
    public array $selectedTraits = [];

    /** Paneli aç/kapat; açılırken seçim mevcut onaylardan başlatılır. */
    public function openTraitPicker(): void
    {
        $this->showTraitPicker = ! $this->showTraitPicker;
        $this->traitNotice = null;

        if ($this->showTraitPicker) {
            $this->selectedTraits = $this->player->traitEndorsements()
                ->where('endorser_id', Auth::id())
                ->pluck('trait_key')
                ->all();
        }
    }

    /** Seçimi yerel olarak değiştirir (kaydetmez). Olumlu ve olumsuz için ayrı limitler. */
    public function toggleTraitSelection(string $key): void
    {
        abort_unless(array_key_exists($key, \App\Support\PlayerTraits::ALL), 400);
        abort_if($this->player->user_id === Auth::id(), 403); // kendine onay yok

        if (in_array($key, $this->selectedTraits, true)) {
            $this->selectedTraits = array_values(array_diff($this->selectedTraits, [$key]));
            $this->traitNotice = null;

            return;
        }

        $negatifMi = \App\Support\PlayerTraits::isNegative($key);
        $limit = \App\Support\PlayerTraits::limitFor($negatifMi ? 'negative' : 'positive');
        $mevcut = $this->selectedCountByType($negatifMi ? 'negative' : 'positive');

        if ($mevcut >= $limit) {
            $this->traitNotice = $negatifMi
                ? "Bir oyuncuya en fazla {$limit} takılma seçebilirsin — önce birini kaldır."
                : "Bir oyuncuya en fazla {$limit} nitelik onaylayabilirsin — önce birini kaldır.";

            return;
        }

        $this->selectedTraits[] = $key;
        $this->traitNotice = null;
    }

    /** Seçimde bu türden kaç tane var? */
    protected function selectedCountByType(string $type): int
    {
        return collect($this->selectedTraits)
            ->filter(fn ($k) => (\App\Support\PlayerTraits::isNegative($k) ? 'negative' : 'positive') === $type)
            ->count();
    }

    /** Seçimi kaydeder: eklenenler yazılır, kaldırılanlar silinir. */
    public function saveTraits(): void
    {
        abort_if($this->player->user_id === Auth::id(), 403);
        abort_if($this->selectedCountByType('positive') > \App\Support\PlayerTraits::MAX_PER_ENDORSER, 400);
        abort_if($this->selectedCountByType('negative') > \App\Support\PlayerTraits::MAX_NEGATIVE_PER_ENDORSER, 400);

        $valid = collect($this->selectedTraits)
            ->filter(fn ($k) => array_key_exists($k, \App\Support\PlayerTraits::ALL))
            ->unique()->values();

        $current = $this->player->traitEndorsements()
            ->where('endorser_id', Auth::id())
            ->pluck('trait_key');

        $this->player->traitEndorsements()
            ->where('endorser_id', Auth::id())
            ->whereIn('trait_key', $current->diff($valid))
            ->delete();

        foreach ($valid->diff($current) as $key) {
            $this->player->traitEndorsements()->create(['trait_key' => $key, 'endorser_id' => Auth::id()]);

            // Tam 3. onayda oyuncuya tek push — takılmalar için bildirim YOK
            if (! \App\Support\PlayerTraits::isNegative($key)
                && $this->player->traitEndorsements()->where('trait_key', $key)->count() === 3) {
                app(\App\Services\PushNotifier::class)->traitMilestone($this->player, $key);
            }
        }

        $this->traitNotice = '✓ Kaydedildi.';
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

        $sayilar = $this->player->traitEndorsements()
            ->selectRaw('trait_key, count(*) as c')
            ->groupBy('trait_key')
            ->orderByDesc('c')
            ->pluck('c', 'trait_key');

        return view('livewire.groups.player-profile', [
            'stats' => $stats,
            'badges' => $badges->evaluate($stats),
            'otherPlayers' => $this->group->players()->whereKeyNot($this->player->id)->orderBy('name')->get(),
            'compare' => $compare,
            'compareStats' => $compareStats,
            'myEarned' => $earnedCount($stats),
            'compareEarned' => $compareStats !== null ? $earnedCount($compareStats) : null,
            'bestPartner' => app(\App\Services\TeamChemistry::class)->bestPartnerFor($this->player->id, $this->group),
            'formHistory' => $this->player->isGuest() ? collect() : $this->player->performanceHistory(),
            // Olumlu nitelikler her onaydan itibaren görünür
            'traitCounts' => $sayilar->reject(fn ($c, $k) => \App\Support\PlayerTraits::isNegative($k)),
            // Takılmalar ancak eşiği geçince görünür — tek kişi yapıştıramaz
            'negativeCounts' => $sayilar
                ->filter(fn ($c, $k) => \App\Support\PlayerTraits::isNegative($k)
                    && $c >= \App\Support\PlayerTraits::MIN_NEGATIVE_VISIBLE),
            'myTraits' => $this->player->traitEndorsements()
                ->where('endorser_id', Auth::id())
                ->pluck('trait_key'),
        ]);
    }
}
