<div class="py-6 sm:py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Karşılama --}}
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-sm text-pitch-muted">{{ now()->translatedFormat('l, d F') }}</div>
                <h2 class="font-display uppercase tracking-wide text-3xl sm:text-4xl font-extrabold leading-none mt-1 truncate">Selam, {{ \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ') }}</h2>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                @if ($kehanetGroup)
                    <a href="{{ route('groups.kehanet', $kehanetGroup) }}" wire:navigate
                       class="inline-flex items-center gap-1.5 min-h-[40px] ps-1.5 pe-3 rounded-full bg-pitch-surface border border-pitch-line hover:border-gold/60 transition"
                       title="Çim bakiyen">
                        <span class="w-7 h-7 rounded-full bg-gold text-[#4A3300] text-xs font-bold flex items-center justify-center">Ç</span>
                        <span class="text-sm font-semibold">{{ number_format($cimBalance) }}</span>
                    </a>
                @endif
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-tutorial'))"
                        class="min-h-[40px] px-3 rounded-full bg-pitch-surface border border-pitch-line text-sm hover:border-pitch-muted/60 transition">
                    🎓 Rehber
                </button>
            </div>
        </div>

        @if ($groups->isEmpty())
            <div class="rounded-3xl border border-bibB/30 bg-gradient-to-br from-pitch-surface2 to-pitch-surface p-6 sm:p-8">
                <h3 class="font-display uppercase tracking-wide text-2xl font-extrabold">İlk grubunu kur</h3>
                <p class="text-pitch-muted mt-2 max-w-xl">Henüz bir grubun yok. Grubu kurup davet linkiyle arkadaşlarını çağır, maç açıp dengeli kadrolar kurmaya başla.</p>
                <a href="{{ route('groups.index') }}" wire:navigate class="inline-block mt-5">
                    <x-primary-button type="button">+ İlk Grubunu Kur</x-primary-button>
                </a>
            </div>
        @endif

        <div class="grid lg:grid-cols-5 gap-6 items-start">
            <div class="lg:col-span-3 space-y-6 min-w-0">

                {{-- Sıradaki maç (kahraman kart) --}}
                @if ($next = $upcoming->first())
                    @php
                        $rsvp = $myRsvps[$next->id] ?? null;
                        $cap = max(1, (int) $next->capacity);
                        $filled = min($cap, $next->going_count);
                    @endphp
                    <section class="relative overflow-hidden rounded-3xl border border-bibB/30 bg-gradient-to-br from-pitch-surface2 to-pitch-surface p-5 sm:p-6"
                             x-data="{
                                hedef: {{ $next->starts_at->getTimestamp() * 1000 }},
                                simdi: Date.now(), iv: null,
                                init() { this.iv = setInterval(() => this.simdi = Date.now(), 30000); },
                                destroy() { clearInterval(this.iv); },
                                get kalan() { return Math.max(0, this.hedef - this.simdi); },
                                get gun() { return String(Math.floor(this.kalan / 864e5)).padStart(2, '0'); },
                                get saat() { return String(Math.floor(this.kalan % 864e5 / 36e5)).padStart(2, '0'); },
                                get dakika() { return String(Math.floor(this.kalan % 36e5 / 6e4)).padStart(2, '0'); },
                             }">
                        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 w-44 h-44 rounded-full border-2 border-bibB/10"></div>
                        <div aria-hidden="true" class="pointer-events-none absolute right-6 top-6 w-16 h-16 rounded-full border-2 border-bibB/10"></div>

                        <div class="relative flex items-center gap-2 flex-wrap">
                            <span class="font-display uppercase tracking-wider text-xs font-bold px-2 py-1 rounded-md bg-bibB text-pitch-bg">Sıradaki maç</span>
                            <span class="text-xs text-pitch-muted truncate">{{ $next->group->name }}</span>
                        </div>
                        <div class="relative font-semibold mt-3 truncate">{{ $next->title }}</div>

                        <div class="relative grid grid-cols-3 gap-2 mt-3">
                            @foreach (['gun' => 'gün', 'saat' => 'saat', 'dakika' => 'dakika'] as $k => $etiket)
                                <div class="text-center py-2 rounded-xl bg-pitch-bg/60">
                                    <div class="font-display text-4xl font-extrabold leading-none" x-text="{{ $k }}">--</div>
                                    <div class="text-[11px] text-pitch-muted mt-0.5">{{ $etiket }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="relative text-sm text-pitch-ink/80 mt-3">
                            {{ $next->starts_at->translatedFormat('l · H:i') }}
                            @if ($next->location) · {{ $next->location }} @endif
                        </div>

                        <div class="relative mt-4">
                            @if ($cap <= 24)
                                <div class="grid gap-[3px]" style="grid-template-columns: repeat({{ $cap }}, minmax(0, 1fr))">
                                    @for ($i = 0; $i < $cap; $i++)
                                        <div class="h-2 rounded-sm {{ $i < $filled ? 'bg-bibB' : 'bg-pitch-line' }}"></div>
                                    @endfor
                                </div>
                            @else
                                <div class="h-2 rounded-full bg-pitch-line overflow-hidden"><div class="h-full bg-bibB" style="width: {{ round($filled / $cap * 100) }}%"></div></div>
                            @endif
                            <div class="flex justify-between text-xs mt-1.5">
                                <span><strong>{{ $next->going_count }}/{{ $next->capacity }}</strong> <span class="text-pitch-muted">kişi geliyor</span></span>
                                @if ($next->going_count < $next->capacity)
                                    <span class="text-gold">{{ $next->capacity - $next->going_count }} yer kaldı</span>
                                @else
                                    <span class="text-bibA">Kadro dolu</span>
                                @endif
                            </div>
                        </div>

                        <div class="relative mt-4 flex items-center gap-3">
                            <a href="{{ route('matches.show', $next) }}" wire:navigate
                               class="flex-1 inline-flex items-center justify-center min-h-[52px] rounded-2xl font-display uppercase tracking-wider text-xl font-extrabold transition active:scale-[.98]
                                      {{ $rsvp ? 'bg-pitch-surface2 border border-pitch-line text-pitch-ink hover:border-pitch-muted/60' : 'bg-bibB text-pitch-bg hover:brightness-110' }}">
                                {{ $rsvp ? 'Maça git' : 'Katılımını bildir' }}
                            </a>
                            @if ($rsvp?->status === 'going' && $rsvp->waitlist_position !== null)
                                <span class="shrink-0 text-xs font-semibold px-3 py-2 rounded-full bg-gold/15 text-gold">Yedek {{ $rsvp->waitlist_position }}.</span>
                            @elseif ($rsvp?->status === 'going')
                                <span class="shrink-0 text-xs font-semibold px-3 py-2 rounded-full bg-bibB/15 text-bibB">Geliyorsun ✓</span>
                            @elseif ($rsvp?->status === 'maybe')
                                <span class="shrink-0 text-xs font-semibold px-3 py-2 rounded-full bg-gold/15 text-gold">Belki</span>
                            @elseif ($rsvp?->status === 'not_going')
                                <span class="shrink-0 text-xs font-semibold px-3 py-2 rounded-full bg-red-500/15 text-[#FF8A8A]">Gelmiyorsun</span>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Bekleyen işler --}}
                @if ($pending['total'] > 0)
                    <section class="space-y-2">
                        <h3 class="font-display uppercase tracking-wider text-lg font-bold text-gold">Seni bekleyenler · {{ $pending['total'] }}</h3>
                        @foreach ($pending['needsRsvp'] as $match)
                            <a href="{{ route('matches.show', $match) }}" wire:navigate
                               class="flex items-center gap-3 rounded-2xl bg-pitch-surface border border-pitch-line px-4 py-3 hover:border-bibB/50 transition">
                                <span class="w-2 self-stretch rounded-full bg-bibB"></span>
                                <span class="min-w-0 flex-1"><span class="block font-semibold text-bibB">Katılım bildir</span><span class="block text-sm text-pitch-muted truncate">{{ $match->title }} · {{ $match->group->name }}</span></span>
                                <span class="text-pitch-muted text-sm shrink-0">{{ $match->starts_at->diffForHumans() }} →</span>
                            </a>
                        @endforeach
                        @foreach ($pending['squadVotes'] as $match)
                            <a href="{{ route('matches.show', $match) }}" wire:navigate
                               class="flex items-center gap-3 rounded-2xl bg-pitch-surface border border-pitch-line px-4 py-3 hover:border-gold/50 transition">
                                <span class="w-2 self-stretch rounded-full bg-gold"></span>
                                <span class="min-w-0 flex-1"><span class="block font-semibold text-gold">Kadroyu oyla</span><span class="block text-sm text-pitch-muted truncate">{{ $match->title }} · {{ $match->group->name }}</span></span>
                                <span class="text-pitch-muted text-sm shrink-0">oylama açık →</span>
                            </a>
                        @endforeach
                        @foreach ($pending['mvpVotes'] as $match)
                            @php $mvpH = (int) ceil(now()->diffInHours($match->mvp_closes_at, true)); @endphp
                            <a href="{{ route('matches.show', $match) }}" wire:navigate
                               class="flex items-center gap-3 rounded-2xl bg-pitch-surface border border-pitch-line px-4 py-3 hover:border-gold/50 transition">
                                <span class="w-2 self-stretch rounded-full bg-gold"></span>
                                <span class="min-w-0 flex-1"><span class="block font-semibold text-gold">MVP seç 🏆</span><span class="block text-sm text-pitch-muted truncate">{{ $match->title }} · {{ $match->group->name }}</span></span>
                                <span class="text-pitch-muted text-sm shrink-0">{{ $mvpH > 48 ? (int) ceil($mvpH / 24).' gün' : $mvpH.' saat' }} kaldı →</span>
                            </a>
                        @endforeach
                    </section>
                @endif

                {{-- Son maç skorbordu --}}
                @if ($lastMatch)
                    <section class="space-y-2">
                        <div class="flex items-baseline justify-between">
                            <h3 class="font-display uppercase tracking-wider text-lg font-bold">Son maç</h3>
                            <a href="{{ route('matches.show', $lastMatch) }}" wire:navigate class="text-sm text-bibB hover:underline">Detay →</a>
                        </div>
                        <a href="{{ route('matches.show', $lastMatch) }}" wire:navigate
                           class="flex items-stretch rounded-2xl bg-pitch-surface border border-pitch-line overflow-hidden hover:border-pitch-muted/50 transition">
                            <span class="w-1.5 bg-bibA"></span>
                            <span class="flex-1 px-4 py-3">
                                <span class="block font-display uppercase tracking-wider text-sm font-bold text-bibA">Turuncu</span>
                                <span class="block font-display text-5xl font-extrabold leading-none">{{ $lastMatch->team_a_score }}</span>
                            </span>
                            <span class="flex flex-col items-center justify-center px-2 text-center gap-1">
                                <span class="text-xs text-pitch-muted">{{ $lastMatch->starts_at->translatedFormat('d M') }} · {{ $lastMatch->group->name }}</span>
                                @if ($lastMvpName)
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gold/15 text-gold">MVP {{ $lastMvpName }}</span>
                                @endif
                            </span>
                            <span class="flex-1 px-4 py-3 text-end">
                                <span class="block font-display uppercase tracking-wider text-sm font-bold text-bibB">Yeşil</span>
                                <span class="block font-display text-5xl font-extrabold leading-none">{{ $lastMatch->team_b_score }}</span>
                            </span>
                            <span class="w-1.5 bg-bibB"></span>
                        </a>
                    </section>
                @endif

                {{-- Yaklaşan maçlar (sıradakinden sonrakiler) --}}
                <section class="space-y-2">
                    <h3 class="font-display uppercase tracking-wider text-lg font-bold">Yaklaşan Maçlar</h3>
                    @forelse ($upcoming->skip(1) as $match)
                        @php $rsvp = $myRsvps[$match->id] ?? null; @endphp
                        <a href="{{ route('matches.show', $match) }}" wire:navigate
                           class="flex items-center gap-3 rounded-2xl bg-pitch-surface border border-pitch-line px-4 py-3 hover:border-pitch-muted/50 transition">
                            <span class="text-center w-12 shrink-0">
                                <span class="block font-display text-2xl font-extrabold leading-none">{{ $match->starts_at->format('d') }}</span>
                                <span class="block text-[11px] uppercase text-pitch-muted">{{ $match->starts_at->translatedFormat('M') }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold truncate">{{ $match->title }}</span>
                                <span class="block text-sm text-pitch-muted truncate">{{ $match->group->name }} · {{ $match->starts_at->translatedFormat('l H:i') }}</span>
                            </span>
                            <span class="font-display text-xl font-bold shrink-0 {{ $match->going_count >= $match->capacity ? 'text-bibA' : '' }}">{{ $match->going_count }}<span class="text-pitch-muted text-sm">/{{ $match->capacity }}</span></span>
                        </a>
                    @empty
                        <p class="text-sm text-pitch-muted rounded-2xl border border-dashed border-pitch-line px-4 py-5 text-center">
                            {{ $upcoming->isEmpty() ? 'Yaklaşan maç yok.' : 'Sıradaki maçtan sonra planlanmış maç yok.' }}
                            @if ($groups->isNotEmpty() && $upcoming->isEmpty())
                                <span class="block mt-1">Grup sayfandan yeni bir maç açabilirsin.</span>
                            @endif
                        </p>
                    @endforelse
                </section>
            </div>

            <div class="lg:col-span-2 space-y-6 min-w-0">
                {{-- Formdakiler: oyuncu kartları --}}
                @if ($formPlayers->isNotEmpty())
                    <section class="space-y-3">
                        <div class="flex items-baseline justify-between">
                            <h3 class="font-display uppercase tracking-wider text-lg font-bold">Formdakiler</h3>
                            <a href="{{ route('groups.stats', $mainGroup) }}" wire:navigate class="text-sm text-bibB hover:underline">Tümü →</a>
                        </div>
                        <div class="grid grid-cols-3 gap-2.5">
                            @foreach ($formPlayers as $p)
                                <x-player-card :player="$p" :group="$mainGroup" />
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Kişisel istatistik --}}
                @if ($stats['played'] > 0 || $groups->isNotEmpty())
                    <section class="grid grid-cols-2 gap-2.5">
                        @php
                            $cards = [
                                ['label' => 'Maç', 'value' => $stats['played'], 'sub' => $stats['wins'].' galibiyet'],
                                ['label' => 'Kazanma', 'value' => '%'.$stats['winRate'], 'sub' => $stats['played'].' maçta'],
                                ['label' => 'Gol', 'value' => $stats['goals'], 'sub' => 'toplam'],
                                ['label' => 'MVP oyu', 'value' => $stats['mvpVotes'], 'sub' => 'toplam'],
                            ];
                        @endphp
                        @foreach ($cards as $card)
                            <div class="rounded-2xl bg-pitch-surface border border-pitch-line px-4 py-3">
                                <div class="font-display text-3xl font-extrabold leading-none">{{ $card['value'] }}</div>
                                <div class="text-sm font-semibold mt-1">{{ $card['label'] }}</div>
                                <div class="text-xs text-pitch-muted">{{ $card['sub'] }}</div>
                            </div>
                        @endforeach
                    </section>
                @endif

                {{-- Gruplarım --}}
                @if ($groups->isNotEmpty())
                    <section class="space-y-2">
                        <div class="flex items-baseline justify-between">
                            <h3 class="font-display uppercase tracking-wider text-lg font-bold">Gruplarım</h3>
                            <a href="{{ route('groups.index') }}" wire:navigate class="text-sm text-bibB hover:underline">Tümü →</a>
                        </div>
                        @foreach ($groups as $group)
                            <a href="{{ route('groups.show', $group) }}" wire:navigate
                               class="flex items-center gap-3 rounded-2xl bg-pitch-surface border border-pitch-line p-3 hover:border-pitch-muted/50 transition">
                                <span class="w-11 h-11 rounded-xl bg-bibB/10 text-bibB flex items-center justify-center font-display text-xl font-extrabold shrink-0">
                                    {{ mb_strtoupper(mb_substr($group->name, 0, 1)) }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold truncate">{{ $group->name }}</span>
                                    <span class="block text-xs text-pitch-muted">
                                        {{ $group->members_count }} üye
                                        @if ($group->owner_id === auth()->id()) · <span class="text-gold">Başkan</span> @endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </section>
                @endif
            </div>
        </div>
    </div>

    {{-- Tanıtım turu: ilk girişte otomatik açılır, "Rehber" butonuyla tekrar açılabilir --}}
    <x-tutorial-modal :auto-open="auth()->user()->tutorial_seen_at === null" />
</div>
