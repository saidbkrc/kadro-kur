<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Karşılama afişi --}}
        <div class="relative overflow-hidden rounded-2xl border border-pitch-line bg-gradient-to-br from-pitch-surface2 to-pitch-surface p-6 sm:p-8">
            <div class="absolute -right-8 -top-10 text-[160px] leading-none opacity-10 select-none pointer-events-none">⚽</div>
            <div class="relative">
                <div class="text-sm text-pitch-muted">{{ now()->translatedFormat('l, d F Y') }}</div>
                <h2 class="font-display uppercase tracking-wider text-3xl font-bold mt-1">Merhaba, {{ auth()->user()->name }} 👋</h2>
                @if ($groups->isEmpty())
                    <p class="text-pitch-muted mt-2 max-w-xl">Henüz bir grubun yok. İlk grubunu kurup davet linkiyle arkadaşlarını çağır, maç açıp dengeli kadrolar kurmaya başla.</p>
                    <a href="{{ route('groups.index') }}" wire:navigate class="inline-block mt-4">
                        <x-primary-button type="button">+ İlk Grubunu Kur</x-primary-button>
                    </a>
                @else
                    <p class="text-pitch-muted mt-2">
                        {{ $groups->count() }} grup ·
                        @if ($upcoming->isNotEmpty())
                            sıradaki maç <strong class="text-pitch-ink">{{ $upcoming->first()->starts_at->diffForHumans() }}</strong>
                        @else
                            yaklaşan maç yok
                        @endif
                    </p>
                @endif
            </div>
        </div>

        {{-- Kişisel istatistik kartları --}}
        @if ($stats['played'] > 0 || $groups->isNotEmpty())
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $cards = [
                        ['label' => 'Oynadığın Maç', 'value' => $stats['played'], 'icon' => '🏟️', 'sub' => $stats['wins'].' galibiyet'],
                        ['label' => 'Kazanma Oranı', 'value' => '%'.$stats['winRate'], 'icon' => '📈', 'sub' => $stats['played'].' maçta'],
                        ['label' => 'Attığın Gol', 'value' => $stats['goals'], 'icon' => '⚽', 'sub' => 'toplam'],
                        ['label' => 'Aldığın MVP Oyu', 'value' => $stats['mvpVotes'], 'icon' => '🏆', 'sub' => 'toplam'],
                    ];
                @endphp
                @foreach ($cards as $card)
                    <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] uppercase tracking-widest text-pitch-muted">{{ $card['label'] }}</span>
                            <span class="text-lg">{{ $card['icon'] }}</span>
                        </div>
                        <div class="font-display text-3xl font-bold mt-1">{{ $card['value'] }}</div>
                        <div class="text-xs text-pitch-muted mt-0.5">{{ $card['sub'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Bekleyen işler --}}
        @if ($pending['total'] > 0)
            <div class="bg-pitch-surface border border-gold/40 rounded-xl p-6 space-y-3">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-gold">⏳ Seni Bekleyenler</h3>
                <div class="space-y-2">
                    @foreach ($pending['needsRsvp'] as $match)
                        <a href="{{ route('matches.show', $match) }}" wire:navigate
                           class="flex items-center justify-between gap-3 border border-pitch-line rounded-lg px-4 py-3 hover:bg-pitch-surface2 transition">
                            <span class="min-w-0 break-words"><span class="text-bibB font-semibold">Katılım bildir</span> · {{ $match->title }} <span class="text-pitch-muted text-sm">({{ $match->group->name }})</span></span>
                            <span class="text-pitch-muted text-sm shrink-0">{{ $match->starts_at->diffForHumans() }} →</span>
                        </a>
                    @endforeach
                    @foreach ($pending['squadVotes'] as $match)
                        <a href="{{ route('matches.show', $match) }}" wire:navigate
                           class="flex items-center justify-between gap-3 border border-pitch-line rounded-lg px-4 py-3 hover:bg-pitch-surface2 transition">
                            <span class="min-w-0 break-words"><span class="text-gold font-semibold">Kadroyu oyla</span> · {{ $match->title }} <span class="text-pitch-muted text-sm">({{ $match->group->name }})</span></span>
                            <span class="text-pitch-muted text-sm shrink-0">oylama açık →</span>
                        </a>
                    @endforeach
                    @foreach ($pending['mvpVotes'] as $match)
                        <a href="{{ route('matches.show', $match) }}" wire:navigate
                           class="flex items-center justify-between gap-3 border border-pitch-line rounded-lg px-4 py-3 hover:bg-pitch-surface2 transition">
                            <span class="min-w-0 break-words"><span class="text-gold font-semibold">MVP seç 🏆</span> · {{ $match->title }} <span class="text-pitch-muted text-sm">({{ $match->group->name }})</span></span>
                            <span class="text-pitch-muted text-sm shrink-0">{{ (int) ceil(now()->diffInHours($match->mvp_closes_at, true)) }} saat kaldı →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- Yaklaşan maçlar --}}
            <div class="lg:col-span-2 bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Yaklaşan Maçlar</h3>

                @forelse ($upcoming as $match)
                    @php $rsvp = $myRsvps[$match->id] ?? null; @endphp
                    <a href="{{ route('matches.show', $match) }}" wire:navigate
                       class="block border border-pitch-line rounded-lg p-4 hover:bg-pitch-surface2 transition">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold truncate">{{ $match->title }}</div>
                                <div class="text-sm text-pitch-muted mt-0.5 truncate">
                                    {{ $match->group->name }} · {{ $match->starts_at->translatedFormat('d F, l H:i') }}
                                    @if ($match->location) · 📍 {{ $match->location }} @endif
                                </div>
                                <div class="text-xs text-bibB mt-1">{{ $match->starts_at->diffForHumans() }}</div>
                            </div>
                            <div class="shrink-0 text-end space-y-1">
                                <div class="font-display text-xl font-bold {{ $match->going_count >= $match->capacity ? 'text-bibA' : '' }}">
                                    {{ $match->going_count }}<span class="text-pitch-muted text-sm">/{{ $match->capacity }}</span>
                                </div>
                                @if ($rsvp?->status === 'going' && $rsvp->waitlist_position !== null)
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Yedek {{ $rsvp->waitlist_position }}.</span>
                                @elseif ($rsvp?->status === 'going')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-bibB/15 text-bibB">Geliyorsun ✓</span>
                                @elseif ($rsvp?->status === 'maybe')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Belki</span>
                                @elseif ($rsvp?->status === 'not_going')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-500/15 text-[#FF8A8A]">Gelmiyorsun</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-pitch-surface2 text-pitch-muted">Cevap bekleniyor</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-10 text-pitch-muted">
                        <div class="text-4xl mb-2">📅</div>
                        Yaklaşan maç yok.
                        @if ($groups->isNotEmpty())
                            <div class="text-sm mt-1">Grup sayfandan yeni bir maç açabilirsin.</div>
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Gruplarım --}}
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Gruplarım</h3>
                    <a href="{{ route('groups.index') }}" wire:navigate class="text-xs text-bibB hover:underline">Tümü →</a>
                </div>

                @forelse ($groups as $group)
                    <a href="{{ route('groups.show', $group) }}" wire:navigate
                       class="flex items-center gap-3 border border-pitch-line rounded-lg p-3 hover:bg-pitch-surface2 transition">
                        <div class="w-10 h-10 rounded-lg bg-bibB/10 text-bibB flex items-center justify-center font-display text-lg font-bold shrink-0">
                            {{ mb_strtoupper(mb_substr($group->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $group->name }}</div>
                            <div class="text-xs text-pitch-muted">
                                {{ $group->members_count }} üye
                                @if ($group->owner_id === auth()->id()) · <span class="text-gold">Başkan</span> @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="text-pitch-muted text-sm">Henüz grubun yok.</p>
                    <a href="{{ route('groups.index') }}" wire:navigate>
                        <x-primary-button type="button">+ Grup Kur</x-primary-button>
                    </a>
                @endforelse
            </div>
        </div>
    </div>
</div>
