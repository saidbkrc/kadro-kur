<div class="py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <a href="{{ route('groups.stats', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← İstatistikler</a>
        </div>

        {{-- Oyuncu başlığı --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6 flex items-center gap-4">
            <x-ovr-badge :player="$player" numClass="text-4xl w-16" />
            <div class="min-w-0">
                <h2 class="font-display uppercase tracking-wider text-2xl font-bold truncate">{{ $player->name }}</h2>
                <div class="text-sm text-pitch-muted mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                    @if (!empty($player->positions))
                        <span class="font-semibold text-pitch-ink">{{ implode(' · ', $player->positions) }}</span>
                    @endif
                    <span>Ayak: {{ $player->footBadge() }}</span>
                    @if ($player->shirt_number)
                        <span>#{{ $player->shirt_number }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sezon özeti --}}
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
            @php
                $cards = [
                    ['label' => 'MAÇ', 'value' => $stats['played'], 'class' => 'text-pitch-ink'],
                    ['label' => 'GALİBİYET', 'value' => $stats['win'], 'class' => 'text-[#7DE39A]'],
                    ['label' => 'GOL', 'value' => $stats['goals'], 'class' => 'text-gold'],
                    ['label' => 'MVP', 'value' => $stats['mvp'], 'class' => 'text-gold'],
                    ['label' => 'KAZANMA', 'value' => $stats['played'] > 0 ? '%'.round($stats['win'] / $stats['played'] * 100) : '–', 'class' => 'text-pitch-ink'],
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="bg-pitch-surface border border-pitch-line rounded-xl p-3 text-center">
                    <div class="font-display font-extrabold text-2xl {{ $card['class'] }}">{{ $card['value'] }}</div>
                    <div class="text-[10px] tracking-[.12em] text-pitch-muted mt-0.5">{{ $card['label'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Rozetler --}}
        @php
            $earnedCount = collect($badges)->where('earned', true)->count();
            $byGroup = collect($badges)->groupBy('group');
        @endphp
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">🏅 Rozetler</h3>
                <span class="text-sm font-bold text-gold">{{ $earnedCount }}<span class="text-pitch-muted font-normal">/{{ count($badges) }}</span></span>
            </div>

            <div class="space-y-5">
                @foreach ($byGroup as $groupName => $groupBadges)
                    <div>
                        <div class="text-[11px] tracking-[.14em] text-pitch-muted mb-2">{{ mb_strtoupper($groupName, 'UTF-8') }}</div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ($groupBadges as $badge)
                                <x-badge-pill :badge="$badge" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
