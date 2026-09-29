<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\Player;
use App\Models\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Group $group;

    // Yeni maç formu
    public bool $showMatchForm = false;

    public string $title = '';

    public string $location = '';

    public string $starts_at = '';

    public int $capacity = 14;

    /** Oyuncu havuzu sıralaması (URL'de tutulur, sayfa yenilenince korunur). */
    public const SORTS = [
        'puan' => 'Puan',
        'pozisyon' => 'Pozisyon',
        'isim' => 'İsim (A-Z)',
        'mac' => 'Maç sayısı',
        'forma' => 'Forma no',
    ];

    #[Url(as: 'sirala', except: 'puan')]
    public string $sort = 'puan';

    // Misafir oyuncu formu
    public bool $showGuestForm = false;

    public string $guestName = '';

    public ?int $guestNumber = null;

    public string $guestFoot = 'right';

    // Oyuncu düzenleme (sadece başkan/admin)
    public ?int $editingPlayerId = null;

    /** @var list<string> tıklama sırası = öncelik */
    public array $posOrder = [];

    public ?int $editNumber = null;

    public string $editName = '';

    public string $editFoot = 'right';

    // Kural formu
    public ?int $ruleA = null;

    public ?int $ruleB = null;

    public string $ruleType = 'apart';

    // Haftalık maç ayarları
    public bool $showSettings = false;

    public ?int $matchDay = null;

    public ?string $matchTime = null;

    public string $defaultLocation = '';

    public int $groupCapacity = 14;

    public bool $autoSchedule = false;

    public function mount(Group $group): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        $this->group = $group;
        $this->capacity = (int) ($group->capacity ?? 14);
        $this->matchDay = $group->match_day;
        $this->matchTime = $group->match_time ? substr($group->match_time, 0, 5) : null;
        $this->defaultLocation = $group->default_location ?? '';
        $this->groupCapacity = (int) ($group->capacity ?? 14);
        $this->autoSchedule = (bool) $group->auto_schedule;
        $this->location = $group->default_location ?? '';
    }

    /* ---------- maç ---------- */

    public function createMatch()
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $this->validate(
            [
                'title' => 'required|string|min:3|max:100',
                'location' => 'nullable|string|max:100',
                'starts_at' => 'required|date|after:now',
                'capacity' => 'required|integer|min:4|max:24',
            ],
            [
                'title.required' => 'Maç başlığı zorunlu (örn: Salı 21:00 maçı).',
                'starts_at.required' => 'Maç tarihi zorunlu.',
                'starts_at.after' => 'Maç tarihi gelecekte olmalı.',
                'capacity.min' => 'Kapasite en az 4 olmalı.',
                'capacity.max' => 'Kapasite en fazla 24 olabilir.',
            ],
        );

        $match = $this->group->matches()->create([
            'created_by' => Auth::id(),
            'title' => $this->title,
            'location' => $this->location !== '' ? $this->location : null,
            'starts_at' => $this->starts_at,
            'capacity' => $this->capacity,
        ]);

        app(\App\Services\PushNotifier::class)->newMatch($match, Auth::id());

        return $this->redirectRoute('matches.show', $match, navigate: true);
    }

    /* ---------- misafir oyuncu ---------- */

    public function addGuest(): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $this->validate(
            [
                'guestName' => 'required|string|min:2|max:24',
                'guestNumber' => 'nullable|integer|min:1|max:99',
                'guestFoot' => 'required|in:'.implode(',', array_keys(\App\Support\Attributes::FEET)),
            ],
            [
                'guestName.required' => 'Oyuncu adı zorunlu.',
                'guestName.max' => 'İsim en fazla 24 karakter olabilir.',
            ],
        );

        $this->group->players()->create([
            'name' => $this->guestName,
            'shirt_number' => $this->guestNumber,
            'positions' => ['OS'],
            'foot' => $this->guestFoot,
        ]);

        $this->reset('guestName', 'guestNumber', 'guestFoot', 'showGuestForm');
    }

    /**
     * Misafir puanını adım adım artırır/azaltır (yön: +1 / -1). Misafir puanlanamadığı
     * için birkaç kez gelmiş misafirin seviyesini başkan elle yansıtır.
     */
    public function adjustGuestRating(int $playerId, int $yon): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        // İzolasyon: yalnızca bu grubun misafiri
        $guest = $this->group->players()->whereNull('user_id')->findOrFail($playerId);

        $yeni = $guest->overall() + ($yon >= 0 ? 1 : -1) * \App\Models\Player::GUEST_RATING_STEP;
        $yeni = max(\App\Models\Player::GUEST_RATING_MIN, min(\App\Models\Player::GUEST_RATING_MAX, $yeni));

        $guest->forceFill(['guest_rating' => round($yeni, 1)])->save();
    }

    /** Misafir kaydını kayıtlı bir üyeyle eşleştirir; üyenin otomatik açılmış boş kaydı silinir. */
    public function linkGuest(int $playerId, int $userId): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $guest = $this->group->players()->whereNull('user_id')->findOrFail($playerId);
        abort_unless($this->group->members()->whereKey($userId)->exists(), 404);

        // Üyenin gruba katılırken açılan (henüz puansız) kaydı varsa kaldır
        $this->group->players()
            ->where('user_id', $userId)
            ->whereDoesntHave('attributeRatings')
            ->delete();

        if ($this->group->players()->where('user_id', $userId)->exists()) {
            return; // üyenin puanlı bir kaydı zaten var, eşleştirme yapılmaz
        }

        $guest->update(['user_id' => $userId]);
    }

    public function removeGuest(int $playerId): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $this->group->players()->whereNull('user_id')->whereKey($playerId)->delete();
    }

    /* ---------- pozisyon düzenleme ---------- */

    public function editPositions(int $playerId): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $player = $this->group->players()->findOrFail($playerId);

        $this->editingPlayerId = $playerId;
        $this->posOrder = $player->positions ?? [];
        $this->editNumber = $player->shirt_number;
        $this->editName = $player->name;
        $this->editFoot = $player->foot ?? 'right';
    }

    /** Pozisyonlar serbestçe birleşir (KL + DEF gibi); tıklama sırası önceliği belirler. */
    public function togglePosition(string $code): void
    {
        if (! array_key_exists($code, \App\Support\Attributes::POSITIONS)) {
            return;
        }

        if (in_array($code, $this->posOrder, true)) {
            $this->posOrder = array_values(array_filter($this->posOrder, fn ($p) => $p !== $code));
        } else {
            $this->posOrder[] = $code;
        }
    }

    public function savePositions(): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $player = $this->group->players()->findOrFail($this->editingPlayerId);

        if ($this->posOrder === []) {
            $this->addError('posOrder', 'En az bir pozisyon seçmelisin.');

            return;
        }

        $this->validate(
            [
                'editName' => 'required|string|min:2|max:24',
                'editNumber' => 'nullable|integer|min:1|max:99',
                'editFoot' => 'required|in:'.implode(',', array_keys(\App\Support\Attributes::FEET)),
            ],
            [
                'editName.required' => 'Oyuncu adı boş olamaz.',
                'editName.max' => 'İsim en fazla 24 karakter olabilir.',
            ],
        );

        $player->update([
            'name' => $this->editName,
            'positions' => $this->posOrder,
            'shirt_number' => $this->editNumber,
            'foot' => $this->editFoot,
        ]);

        $this->reset('editingPlayerId', 'posOrder', 'editNumber', 'editName', 'editFoot');
    }

    /* ---------- eşleşme kuralları ---------- */

    public function addRule(): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        if (! $this->ruleA || ! $this->ruleB || $this->ruleA === $this->ruleB) {
            $this->addError('ruleA', 'İki farklı oyuncu seçmelisin.');

            return;
        }

        $exists = $this->group->rules()
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('player_a_id', $this->ruleA)->where('player_b_id', $this->ruleB))
                ->orWhere(fn ($q2) => $q2->where('player_a_id', $this->ruleB)->where('player_b_id', $this->ruleA)))
            ->exists();

        if ($exists) {
            $this->addError('ruleA', 'Bu ikili için zaten bir kural var.');

            return;
        }

        $this->group->rules()->create([
            'player_a_id' => $this->ruleA,
            'player_b_id' => $this->ruleB,
            'type' => in_array($this->ruleType, ['apart', 'together'], true) ? $this->ruleType : 'apart',
        ]);

        $this->reset('ruleA', 'ruleB');
    }

    public function deleteRule(int $ruleId): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $this->group->rules()->whereKey($ruleId)->delete();
    }

    /* ---------- üyelik ---------- */

    /** Üye gruptan ayrılır. Başkan ayrılamaz (grubu silmeli). */
    public function leaveGroup()
    {
        abort_if(Auth::id() === $this->group->owner_id, 403);

        $this->detachMember(Auth::id());

        return $this->redirectRoute('groups.index', navigate: true);
    }

    /** Başkan/admin bir üyeyi gruptan çıkarır. */
    public function removeMember(int $userId): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);
        abort_if($userId === $this->group->owner_id, 403); // başkan çıkarılamaz
        abort_if($userId === Auth::id(), 403);              // kendini çıkaramaz (ayrıl kullan)

        $this->detachMember($userId);
    }

    /** Başkan grubu tamamen siler (maçlar, oyuncular, puanlar dahil). */
    public function deleteGroup()
    {
        abort_unless(Auth::id() === $this->group->owner_id, 403);

        $this->group->delete();

        return $this->redirectRoute('groups.index', navigate: true);
    }

    /** Üyeliği kaldırır; geçmişi olan oyuncu kaydı misafire döner, boşsa silinir. */
    protected function detachMember(int $userId): void
    {
        $player = $this->group->players()->where('user_id', $userId)->first();

        if ($player) {
            $hasHistory = $player->attributeRatings()->exists()
                || $player->rsvps()->exists()
                || $player->goals()->exists();

            $hasHistory ? $player->update(['user_id' => null]) : $player->delete();
        }

        $this->group->members()->detach($userId);
    }

    /* ---------- haftalık maç ayarları ---------- */

    public function saveSettings(): void
    {
        abort_unless($this->group->isAdmin(Auth::user()), 403);

        $this->validate(
            [
                'matchDay' => 'nullable|integer|min:1|max:7',
                'matchTime' => 'nullable|date_format:H:i',
                'defaultLocation' => 'nullable|string|max:100',
                'groupCapacity' => 'required|integer|min:4|max:24',
            ],
            [
                'matchTime.date_format' => 'Saat SS:DD biçiminde olmalı (örn. 21:00).',
            ],
        );

        if ($this->autoSchedule && (! $this->matchDay || ! $this->matchTime)) {
            $this->addError('matchDay', 'Otomatik maç için gün ve saat seçmelisin.');

            return;
        }

        $this->group->update([
            'match_day' => $this->matchDay,
            'match_time' => $this->matchTime,
            'default_location' => $this->defaultLocation !== '' ? $this->defaultLocation : null,
            'capacity' => $this->groupCapacity,
            'auto_schedule' => $this->autoSchedule,
        ]);

        if ($this->autoSchedule) {
            $this->group->refresh();
            app(\App\Services\MatchScheduler::class)->ensureUpcomingMatch($this->group);
        }

        $this->showSettings = false;
    }

    /**
     * Havuzu seçilen ölçüte göre sıralar. Her ölçütte eşitlik isimle (Türkçe alfabe) bozulur.
     *
     * Puan sırasında gizli puanlar (eşik oylama sayısına ulaşmamış) en sona, kendi
     * aralarında isimle dizilir — gizli puana göre sıralamak, rozette "?" görünen
     * puanı listedeki yerinden ele verirdi.
     */
    protected function sortPlayers(\Illuminate\Support\Collection $players, \Illuminate\Support\Collection $stats): \Illuminate\Support\Collection
    {
        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'puan';
        $isim = $this->nameComparator();
        $sira = array_flip(array_keys(\App\Support\Attributes::POSITIONS)); // KL, DEF, OS, FV

        $anahtar = fn (Player $p) => match ($sort) {
            // Yüksek puan önce; gizliler en sonda
            'puan' => [$p->overallIsPublic() ? 0 : 1, $p->overallIsPublic() ? -$p->displayRating() : 0],
            // Birinci pozisyona göre (pozisyonsuzlar en sonda), sonra yüksek puan
            'pozisyon' => [$sira[$p->positions[0] ?? ''] ?? 99, $p->overallIsPublic() ? -$p->displayRating() : 0],
            'mac' => [-($stats->get($p->id)['played'] ?? 0)],
            // Forma numarası olmayanlar en sonda
            'forma' => [$p->shirt_number === null ? 1 : 0, $p->shirt_number ?? 0],
            default => [],
        };

        return $players->sort(function (Player $a, Player $b) use ($anahtar, $isim) {
            return ($anahtar($a) <=> $anahtar($b)) ?: $isim($a->name, $b->name);
        })->values();
    }

    /** Türkçe alfabeyle isim karşılaştırıcı (Ç, Ğ, İ, Ö, Ş, Ü doğru yerde). */
    protected function nameComparator(): \Closure
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('tr_TR');

            return fn (string $a, string $b) => $collator->compare($a, $b);
        }

        // intl eklentisi olmayan sunucu için yedek: harfleri Türkçe sıraya eşle
        $harfler = 'aâbcçdefgğhıiîjklmnoöprsştuûüvyz';
        $kod = function (string $s) use ($harfler) {
            $s = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $s), 'UTF-8');

            return implode('', array_map(
                fn ($ch) => ($i = mb_strpos($harfler, $ch)) === false ? $ch : chr(65 + $i),
                mb_str_split($s),
            ));
        };

        return fn (string $a, string $b) => strcmp($kod($a), $kod($b));
    }

    public function render(): View
    {
        // Havuz satırları için: kazanılan rozet ikonları + en çok onaylanan nitelikler
        $badgeService = app(\App\Services\PlayerBadges::class);
        $groupStats = $badgeService->statsForGroup($this->group);   // tüm zamanlar (maç sayısı sırası)
        // Rozetler sezonluk: satırda yalnızca bu sezon kazanılanlar
        $earnedIcons = $badgeService->statsForGroup($this->group, \App\Support\Season::current())->map(
            fn (array $s) => collect($badgeService->evaluate($s))->where('earned', true)->pluck('icon')->all()
        );

        $players = $this->sortPlayers(
            $this->group->players()->with('attributeRatings')->get(),
            $groupStats,
        );

        $topTraits = \App\Models\PlayerTraitEndorsement::whereIn('player_id', $players->pluck('id'))
            ->selectRaw('player_id, trait_key, count(*) as c')
            ->groupBy('player_id', 'trait_key')
            ->orderByDesc('c')
            ->get()
            ->groupBy('player_id')
            ->map(fn ($rows) => $rows
                // Havuz listesinde sadece olumlu nitelikler — takılmalar profilde kalır
                ->reject(fn ($r) => \App\Support\PlayerTraits::isNegative($r->trait_key))
                ->take(2)
                ->map(fn ($r) => ['key' => $r->trait_key, 'c' => $r->c] + (\App\Support\PlayerTraits::ALL[$r->trait_key] ?? []))
                ->filter(fn ($t) => isset($t['name']))
                ->values());

        return view('livewire.groups.show', [
            'players' => $players,
            'earnedIcons' => $earnedIcons,
            'topTraits' => $topTraits,
            'myPlayer' => $this->group->playerFor(Auth::user()),
            'unlinkedMembers' => $this->group->members()
                ->whereNotIn('users.id', $this->group->players()->whereNotNull('user_id')->pluck('user_id'))
                ->orderBy('name')
                ->get(),
            'rules' => $this->group->rules()->with(['playerA', 'playerB'])->get(),
            'upcoming' => $this->group->matches()
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->withCount(['rsvps as going_count' => fn ($q) => $q->where('status', 'going')->whereNull('waitlist_position')])
                ->get(),
            'past' => $this->group->matches()
                ->where(fn ($q) => $q->where('starts_at', '<', now())->orWhere('status', '!=', 'scheduled'))
                ->orderByDesc('starts_at')
                ->limit(10)
                ->get(),
            'isAdmin' => $this->group->isAdmin(Auth::user()),
        ]);
    }
}
