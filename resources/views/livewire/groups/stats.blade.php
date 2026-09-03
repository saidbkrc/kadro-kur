<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-group-nav :group="$group" active="stats" />

        <div>
            <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← {{ $group->name }}</a>
            <h2 class="font-display uppercase tracking-wider text-2xl font-bold mt-1">İstatistikler</h2>
        </div>

        {{-- Oyuncu arama --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-3">
            <div class="flex items-center gap-2">
                <span class="text-pitch-muted text-sm shrink-0">🔍</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Oyuncu ara…"
                       class="w-full bg-pitch-bg border-pitch-line text-pitch-ink placeholder-pitch-muted/60 rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                @if ($search !== '')
                    <button wire:click="$set('search', '')" class="shrink-0 text-xs text-pitch-muted hover:text-pitch-ink px-2">Temizle</button>
                @endif
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 items-start">
            <div class="space-y-6 min-w-0">
                
                {{-- Gol krallığı --}}
                <div class="min-w-0 bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">👑 Gol Krallığı</h3>
                    @if ($topScorers->isEmpty())
                        <p class="text-pitch-muted text-sm">Henüz gol kaydı yok — maç sonucu girerken golleri atanları da işaretle.</p>
                    @else
                        <div class="overflow-x-auto -mx-4 px-4 sm:-mx-6 sm:px-6">
                            <table class="w-full min-w-[420px] text-sm">
                                <tr class="text-[11px] tracking-[.12em] text-pitch-muted text-start">
                                    <th class="text-start py-2 px-2 border-b border-pitch-line"></th>
                                    <th class="text-start py-2 px-2 border-b border-pitch-line">OYUNCU</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">GOL</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">MAÇ</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">GOL/MAÇ</th>
                                </tr>
                                @foreach ($topScorers as $i => $s)
                                    <tr>
                                        <td class="py-2 px-2 border-b border-pitch-line font-display font-bold {{ $i === 0 ? 'text-gold' : 'text-pitch-muted' }} w-9">{{ $i === 0 ? '👑' : ($i + 1).'.' }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line font-semibold">
                                            <a href="{{ route('groups.player', [$group, $s['player']]) }}" wire:navigate class="hover:text-bibB hover:underline">{{ $s['player']->name }}</a>
                                        </td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center font-extrabold text-gold">{{ $s['goals'] }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['played'] }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['played'] > 0 ? number_format($s['goals'] / $s['played'], 1) : '–' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Takım kimyası --}}
                <div class="min-w-0 bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-1">🧪 Takım Kimyası</h3>
                    <p class="text-xs text-pitch-muted mb-3">Aynı takımda en çok kazanan ikililer (en az {{ \App\Services\TeamChemistry::MIN_TOGETHER }} ortak maç).</p>
                    @if ($chemistry->isEmpty())
                        <p class="text-pitch-muted text-sm">Henüz yeterli veri yok — ikililer aynı takımda {{ \App\Services\TeamChemistry::MIN_TOGETHER }} maç oynayınca burada belirir.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($chemistry as $i => $pair)
                                <div class="flex items-center gap-3">
                                    <span class="font-display font-bold w-6 text-center {{ $i === 0 ? 'text-gold' : 'text-pitch-muted' }}">{{ $i === 0 ? '🧪' : ($i + 1).'.' }}</span>
                                    <span class="text-sm font-semibold min-w-0 truncate">{{ $pair['a']->name }} <span class="text-pitch-muted font-normal">+</span> {{ $pair['b']->name }}</span>
                                    <div class="ms-auto flex items-center gap-2 shrink-0">
                                        <div class="w-20 h-1.5 rounded-full bg-pitch-bg border border-pitch-line overflow-hidden hidden sm:block">
                                            <div class="h-full bg-gradient-to-r from-pitch-green to-bibB" style="width: {{ $pair['rate'] }}%"></div>
                                        </div>
                                        <span class="font-display font-bold text-bibB w-12 text-end">%{{ $pair['rate'] }}</span>
                                        <span class="text-xs text-pitch-muted w-12 text-end">{{ $pair['together'] }} maç</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Oyuncu istatistikleri --}}
                <div class="min-w-0 bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">Oyuncu İstatistikleri</h3>
                    @if ($playerStats->isEmpty())
                        <p class="text-pitch-muted text-sm">Henüz istatistik yok — ilk maç sonucu kaydedildiğinde burası dolacak.</p>
                    @else
                        <div class="overflow-x-auto -mx-4 px-4 sm:-mx-6 sm:px-6">
                            <table class="w-full min-w-[520px] text-sm">
                                <tr class="text-[11px] tracking-[.12em] text-pitch-muted">
                                    <th class="text-start py-2 px-2 border-b border-pitch-line">OYUNCU</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">MAÇ</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">G</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">B</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">M</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">⚽</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">⭐</th>
                                    <th class="text-center py-2 px-2 border-b border-pitch-line">KAZANMA</th>
                                </tr>
                                @foreach ($playerStats as $s)
                                    <tr>
                                        <td class="py-2 px-2 border-b border-pitch-line font-semibold">
                                            <a href="{{ route('groups.player', [$group, $s['player']]) }}" wire:navigate class="hover:text-bibB hover:underline">{{ $s['player']->name }}</a>
                                            @foreach (array_slice($earnedIcons[$s['player']->id] ?? [], 0, 5) as $icon)<span class="ms-0.5">{{ $icon }}</span>@endforeach
                                        </td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['played'] }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center text-[#7DE39A]">{{ $s['win'] }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center text-pitch-muted">{{ $s['draw'] }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center text-[#FF8A8A]">{{ $s['loss'] }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['goals'] ?: '–' }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['mvp'] ?: '–' }}</td>
                                        <td class="py-2 px-2 border-b border-pitch-line text-center font-bold">%{{ $s['played'] > 0 ? round($s['win'] / $s['played'] * 100) : 0 }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Maç geçmişi --}}
            <div class="min-w-0 bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">Maç Geçmişi <span class="text-xs text-pitch-muted font-normal tracking-widest">{{ $totalMatches }} MAÇ</span></h3>
                @if ($matches->isEmpty())
                    <p class="text-pitch-muted text-sm">Kayıtlı maç yok. Maç sayfasında takımları kurup maç sonrası skoru kaydet.</p>
                @endif
                <div class="divide-y divide-pitch-line">
                    @foreach ($matches as $match)
                        @php
                            $teamNames = fn ($team) => $match->rsvps
                                ->filter(fn ($r) => $r->team === $team)
                                ->map(fn ($r) => $r->player?->name)
                                ->filter()
                                ->implode(', ');
                            $goalText = $match->goals
                                ->sortByDesc('count')
                                ->map(fn ($g) => $g->player?->name.($g->count > 1 ? ' ×'.$g->count : ''))
                                ->filter()
                                ->implode(', ');
                            $mvpCounts = $match->mvpVotes->countBy('player_id');
                            $mvpName = null;
                            if (! $match->mvpOpen() && $mvpCounts->isNotEmpty()) {
                                $topId = $mvpCounts->sortDesc()->keys()->first();
                                $mvpName = $match->mvpVotes->firstWhere('player_id', $topId)?->player?->name;
                            }
                        @endphp
                        <a href="{{ route('matches.show', $match) }}" wire:navigate class="block py-3 sm:py-4 hover:bg-pitch-surface2 rounded-lg px-2 -mx-2 transition">
                            <div class="text-xs text-pitch-muted">{{ $match->starts_at->translatedFormat('d F Y, l') }}</div>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mt-1.5 sm:mt-1">
                                <span class="font-display text-2xl font-bold whitespace-nowrap shrink-0">
                                    <span class="text-bibA {{ $match->team_a_score > $match->team_b_score ? 'underline underline-offset-4' : '' }}">{{ $match->team_a_score }}</span>
                                    :
                                    <span class="text-bibB {{ $match->team_b_score > $match->team_a_score ? 'underline underline-offset-4' : '' }}">{{ $match->team_b_score }}</span>
                                </span>
                                <span class="text-xs text-pitch-muted leading-relaxed min-w-0 break-words">
                                    @if ($teamNames('A'))<b class="text-bibA">Turuncu:</b> {{ $teamNames('A') }}<br>@endif
                                    @if ($teamNames('B'))<b class="text-bibB">Yeşil:</b> {{ $teamNames('B') }}@endif
                                </span>
                            </div>

                            @if ($goalText || $mvpName)
                                <div class="text-xs text-pitch-muted mt-2 min-w-0 break-words">
                                    @if ($goalText)⚽ <b class="text-pitch-ink">{{ $goalText }}</b>@endif
                                    @if ($goalText && $mvpName) &nbsp;·&nbsp; @endif
                                    @if ($mvpName)⭐ Maçın adamı: <b class="text-gold">{{ $mvpName }}</b>@endif
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>

                @if ($matches->count() < $totalMatches)
                    <div class="pt-3 text-center">
                        <x-secondary-button wire:click="loadMoreMatches" class="w-full sm:w-auto">
                            Daha fazla göster ({{ $matches->count() }}/{{ $totalMatches }})
                        </x-secondary-button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>