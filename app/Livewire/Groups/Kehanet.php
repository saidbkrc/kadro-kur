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
use Livewire\Attributes\Url;
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

    /** Kombine kupon sepeti: [['match_id'=>, 'market'=>, 'selection'=>, 'label'=>, 'odds'=>], ...] */
    public array $parlay = [];

    public int $parlayStake = 20;

    /** Skor tahmini girişleri: ["{matchId}-a" => 3, ...] */
    public array $scorePick = [];

    /** Aktif sekme: kupon | kuponlarim | kahin | cim | olaylar */
    #[Url(as: 'sekme')]
    public string $tab = 'kupon';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->notice = null;
    }

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

        // Bakiye anında güncellensin (Auth::user() örneği bellekte eskiyor)
        Auth::user()->refresh();
    }

    /** Skor tam tahmini: iki sayıdan "A-B" seçimi kurar. */
    public function betScore(int $matchId): void
    {
        $a = (int) ($this->scorePick["{$matchId}-a"] ?? -1);
        $b = (int) ($this->scorePick["{$matchId}-b"] ?? -1);

        if ($a < 0 || $b < 0 || $a > 20 || $b > 20) {
            $this->notice = 'Geçerli bir skor gir (0-20).';

            return;
        }

        $this->selection["{$matchId}-exact_score"] = "{$a}-{$b}";
        $this->bet($matchId, 'exact_score');
    }

    /** Kombine sepetine tahmin ekler/çıkarır. */
    public function toggleParlay(int $matchId, string $market, string $selection): void
    {
        $anahtar = "{$matchId}-{$market}";

        foreach ($this->parlay as $i => $bacak) {
            if ($bacak['key'] === $anahtar) {
                unset($this->parlay[$i]);
                $this->parlay = array_values($this->parlay);
                $this->notice = null;

                return;
            }
        }

        if (count($this->parlay) >= K::MAX_LEGS) {
            $this->notice = 'Kombineye en fazla '.K::MAX_LEGS.' tahmin eklenebilir.';

            return;
        }

        $match = $this->group->matches()->findOrFail($matchId);

        $this->parlay[] = [
            'key' => $anahtar,
            'match_id' => $matchId,
            'market' => $market,
            'selection' => $selection,
            'label' => K::icon($market).' '.$this->selectionText($market, $selection),
            'odds' => app(OddsCalculator::class)->odds($match, $market, $selection),
        ];
        $this->notice = null;
    }

    public function clearParlay(): void
    {
        $this->parlay = [];
    }

    /** Kombine kuponu oynar. */
    public function placeParlay(): void
    {
        $bacaklar = array_map(fn ($b) => [
            'match_id' => $b['match_id'], 'market' => $b['market'], 'selection' => $b['selection'],
        ], $this->parlay);

        $sonuc = app(KehanetService::class)->placeParlay(Auth::user(), $this->group, $bacaklar, $this->parlayStake);
        $this->notice = $sonuc['message'];

        if ($sonuc['ok']) {
            $this->parlay = [];
        }

        Auth::user()->refresh();
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
        $servis = app(KehanetService::class);
        $macIdler = $this->group->matches()->pluck('id');
        $isAdmin = $this->group->isAdmin($user);

        // Bekleyen kupon sayısı (sekme rozeti) — her sekmede lazım, ucuz
        $bekleyenSayisi = Prediction::where('user_id', $user->id)
            ->whereIn('match_id', $macIdler)
            ->where('status', 'pending')
            ->count();

        // Sekme bazlı veri: sadece görünen sekmenin sorguları çalışır
        $veri = [
            'openMatches' => collect(), 'pendingMatches' => collect(), 'myBets' => collect(),
            'mySlips' => collect(), 'transactions' => collect(), 'leaders' => collect(),
            'streaks' => collect(), 'pulse' => collect(), 'line' => 8.5, 'awardStatus' => [],
        ];

        if ($this->tab === 'kupon') {
            $veri['openMatches'] = $this->group->matches()
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(3)
                ->get();

            $veri['pulse'] = $veri['openMatches']->mapWithKeys(fn ($m) => [$m->id => $servis->pulse($m)]);
            $veri['line'] = $odds->totalGoalsLine($this->group);

            // Kilit durumunu göstermek için mevcut kuponlar
            $veri['myBets'] = Prediction::where('user_id', $user->id)
                ->whereIn('match_id', $veri['openMatches']->pluck('id'))
                ->whereNull('slip_id')
                ->where('status', 'pending')
                ->get();
        }

        if ($this->tab === 'kuponlarim') {
            $veri['myBets'] = Prediction::where('user_id', $user->id)
                ->whereIn('match_id', $macIdler)
                ->whereNull('slip_id')
                ->with('match')
                ->orderByDesc('id')
                ->limit(25)
                ->get();

            $veri['mySlips'] = \App\Models\PredictionSlip::where('user_id', $user->id)
                ->where('group_id', $this->group->id)
                ->with('legs.match')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        if ($this->tab === 'kahin') {
            // Ayın Kâhini: bu ay sonuçlanan kuponların net kazancı
            $veri['leaders'] = DB::table('predictions')
                ->join('users', 'users.id', '=', 'predictions.user_id')
                ->whereIn('predictions.match_id', $macIdler)
                ->whereIn('predictions.status', ['won', 'lost'])
                ->where('predictions.settled_at', '>=', now()->startOfMonth())
                ->whereNull('predictions.slip_id')
                ->selectRaw('users.id as user_id, users.name, SUM(predictions.payout) - SUM(predictions.stake) as net,
                             SUM(CASE WHEN predictions.status = "won" THEN 1 ELSE 0 END) as tuttu,
                             COUNT(*) as toplam')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('net')
                ->limit(10)
                ->get();

            $veri['streaks'] = $veri['leaders']->mapWithKeys(fn ($l) => [$l->user_id => $servis->streak($l->user_id, $this->group)]);
        }

        if ($this->tab === 'cim') {
            $veri['transactions'] = \App\Models\CimTransaction::where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(30)
                ->get();
        }

        if ($this->tab === 'oduller') {
            // Maçtan bağımsız ödülleri tembel dağıt (profil, puanlama, rozet)
            app(\App\Services\CimRewards::class)->syncStandingAwards($user, $this->group);
            $user->refresh();
            $veri['awardStatus'] = app(\App\Services\CimRewards::class)->statusFor($user, $this->group);
        }

        if ($this->tab === 'olaylar' && $isAdmin) {
            $veri['pendingMatches'] = $this->group->matches()
                ->where('status', 'completed')
                ->orderByDesc('starts_at')
                ->limit(3)
                ->with('events')
                ->get();
        }

        return view('livewire.groups.kehanet', $veri + [
            'balance' => $user->cim_balance,
            'myStreak' => $servis->streak($user->id, $this->group),
            'pendingCount' => $bekleyenSayisi,
            'odds' => $odds,
            'isAdmin' => $isAdmin,
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
