<?php

namespace App\Livewire\Groups;

use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\MatchEvent;
use App\Models\Prediction;
use App\Services\KehanetService;
use App\Services\OddsCalculator;
use App\Support\Kehanet as K;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Kehanet: eğlence amaçlı tahmin oyunu (sanal "Çim" ile — gerçek para yok). */
#[Layout('layouts.app')]
class Kehanet extends Component
{
    public Group $group;

    /** Kupon formu: [market => seçim] ve tutarlar */
    public array $selection = [];

    public array $stake = [];

    public ?string $notice = null;

    /** Başkan: maç olayı girişi [event_key => player_id] */
    public array $eventPick = [];

    public ?int $eventMatchId = null;

    public function mount(Group $group): void
    {
        abort_unless($group->isMember(Auth::user()), 403);

        $this->group = $group;

        // Haftalık Çim yüklemesi (bu hafta verilmediyse)
        $verilen = app(KehanetService::class)->grantWeeklyIfDue(Auth::user());
        if ($verilen > 0) {
            $this->notice = "🌱 Haftalık {$verilen} Çim hesabına yüklendi!";
        }
    }

    /** Kupon yap. */
    public function bet(int $matchId, string $market): void
    {
        $match = $this->group->matches()->findOrFail($matchId);
        $secim = (string) ($this->selection["{$matchId}-{$market}"] ?? '');
        $tutar = (int) ($this->stake["{$matchId}-{$market}"] ?? 20);

        if ($secim === '') {
            $this->notice = 'Önce bir seçim yap.';

            return;
        }

        $sonuc = app(KehanetService::class)->placeBet(Auth::user(), $match, $market, $secim, $tutar);
        $this->notice = $sonuc['message'];
    }

    public function cancelBet(int $predictionId): void
    {
        $kupon = Prediction::where('user_id', Auth::id())->findOrFail($predictionId);
        $this->notice = app(KehanetService::class)->cancelBet(Auth::user(), $kupon)['message'];
    }

    /** Başkan: maç olaylarını kaydeder ve bekleyen kuponları sonuçlandırır. */
    public function saveEvents(int $matchId): void
    {
        $match = $this->group->matches()->findOrFail($matchId);
        abort_unless($match->canManage(Auth::user()), 403);

        foreach (K::EVENTS as $key => $olay) {
            $playerId = $this->eventPick["{$matchId}-{$key}"] ?? '';

            if ($playerId === '') {
                continue; // dokunulmadı — atla
            }

            // "kimse" seçilirse player_id null kaydedilir (o market'in kuponları kaybeder)
            $gecerli = $playerId === 'yok'
                ? null
                : ($this->group->players()->whereKey($playerId)->value('id'));

            MatchEvent::updateOrCreate(
                ['match_id' => $match->id, 'event_key' => $key],
                ['player_id' => $gecerli],
            );
        }

        $adet = app(KehanetService::class)->settleMatch($match->refresh());
        $this->notice = "✅ Olaylar kaydedildi, {$adet} kupon sonuçlandı.";
    }

    public function render(OddsCalculator $odds): View
    {
        $user = Auth::user();

        // Kupon yapılabilecek maçlar
        $acikMaclar = $this->group->matches()
            ->where('status', 'scheduled')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(3)
            ->get();

        // Başkanın olay girmesi gereken tamamlanmış maçlar
        $bekleyenMaclar = collect();
        if ($this->group->isAdmin($user)) {
            $bekleyenMaclar = $this->group->matches()
                ->where('status', 'completed')
                ->orderByDesc('starts_at')
                ->limit(3)
                ->with('events')
                ->get();
        }

        $kuponlarim = Prediction::where('user_id', $user->id)
            ->whereIn('match_id', $this->group->matches()->pluck('id'))
            ->with('match')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        // Ayın Kâhini: bu ay sonuçlanan kuponların net kazancı
        $liderler = DB::table('predictions')
            ->join('users', 'users.id', '=', 'predictions.user_id')
            ->whereIn('predictions.match_id', $this->group->matches()->pluck('id'))
            ->whereIn('predictions.status', ['won', 'lost'])
            ->where('predictions.settled_at', '>=', now()->startOfMonth())
            ->selectRaw('users.name, SUM(predictions.payout) - SUM(predictions.stake) as net,
                         SUM(CASE WHEN predictions.status = "won" THEN 1 ELSE 0 END) as tuttu,
                         COUNT(*) as toplam')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('net')
            ->limit(10)
            ->get();

        return view('livewire.groups.kehanet', [
            'balance' => $user->cim_balance,
            'openMatches' => $acikMaclar,
            'pendingMatches' => $bekleyenMaclar,
            'myBets' => $kuponlarim,
            'leaders' => $liderler,
            'odds' => $odds,
            'roster' => $this->group->players()->orderBy('name')->get(),
            'isAdmin' => $this->group->isAdmin($user),
            'line' => $odds->totalGoalsLine($this->group),
        ]);
    }

    /** Bir maçın asıl kadrosundaki oyuncular (oyuncu market'lerinin seçenekleri). */
    public function squadFor(FootballMatch $match)
    {
        return $match->mainListRsvps()->map(fn ($r) => $r->player)->filter()->values();
    }

    /** Kadro kurulduysa takım listeleri: ['A' => [...], 'B' => [...]]; kurulmadıysa boş. */
    public function teamsFor(FootballMatch $match): array
    {
        if ($match->squad_status === 'none') {
            return [];
        }

        $rsvps = $match->mainListRsvps();

        $takim = fn ($harf) => $rsvps->where('team', $harf)
            ->map(fn ($r) => $r->player)->filter()->values();

        $a = $takim('A');
        $b = $takim('B');

        return ($a->isEmpty() && $b->isEmpty()) ? [] : ['A' => $a, 'B' => $b];
    }

    /** Oyuncu adları önbelleği (kupon etiketlerinde tekrar tekrar sorgu atmamak için). */
    protected ?array $playerNameCache = null;

    /** Kupon seçiminin okunabilir karşılığı: "Turuncu", "8.5 Üst", "Ahmet". */
    public function selectionText(string $market, string $selection): string
    {
        $kind = K::MARKETS[$market]['kind'] ?? 'oyuncu';

        if ($kind === 'takim') {
            return K::teamOptions($market)[$selection] ?? $selection;
        }

        if ($kind === 'altust') {
            $line = app(OddsCalculator::class)->totalGoalsLine($this->group);

            return $line.($selection === 'over' ? ' Üst' : ' Alt');
        }

        $this->playerNameCache ??= $this->group->players()->pluck('name', 'id')->all();

        return $this->playerNameCache[(int) $selection] ?? 'Bilinmiyor';
    }
}
