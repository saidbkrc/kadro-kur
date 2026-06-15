<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← {{ $group->name }}</a>
            <h2 class="font-display uppercase tracking-wider text-2xl font-bold mt-1">İstatistikler</h2>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 items-start">
            <div class="space-y-6">
                {{-- Gol krallığı --}}
                <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">👑 Gol Krallığı</h3>
                    @if ($topScorers->isEmpty())
                        <p class="text-pitch-muted text-sm">Henüz gol kaydı yok — maç sonucu girerken golleri atanları da işaretle.</p>
                    @else
                        <table class="w-full text-sm">
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
                                    <td class="py-2 px-2 border-b border-pitch-line font-semibold">{{ $s['player']->name }}</td>
                                    <td class="py-2 px-2 border-b border-pitch-line text-center font-extrabold text-gold">{{ $s['goals'] }}</td>
                                    <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['played'] }}</td>
                                    <td class="py-2 px-2 border-b border-pitch-line text-center">{{ $s['played'] > 0 ? number_format($s['goals'] / $s['played'], 1) : '–' }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </div>

                {{-- Oyuncu istatistikleri --}}
                <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">Oyuncu İstatistikleri</h3>
                    @if ($playerStats->isEmpty())
                        <p class="text-pitch-muted text-sm">Henüz istatistik yok — ilk maç sonucu kaydedildiğinde burası dolacak.</p>
                    @else
                        <table class="w-full text-sm">
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
                                    <td class="py-2 px-2 border-b border-pitch-line font-semibold">{{ $s['player']->name }}</td>
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
                    @endif
                </div>
            </div>

            {{-- Maç geçmişi --}}
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">Maç Geçmişi <span class="text-xs text-pitch-muted font-normal tracking-widest">{{ $matches->count() }} MAÇ</span></h3>
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
                        <a href="{{ route('matches.show', $match) }}" wire:navigate class="block py-3 hover:bg-pitch-surface2 rounded-lg px-2 -mx-2 transition">
                            <div class="text-xs text-pitch-muted">{{ $match->starts_at->translatedFormat('d F Y, l') }}</div>
                            <div class="flex items-center gap-4 mt-1">
                                <span class="font-display text-2xl font-bold whitespace-nowrap">
                                    <span class="text-bibA {{ $match->team_a_score > $match->team_b_score ? 'underline underline-offset-4' : '' }}">{{ $match->team_a_score }}</span>
                                    :
                                    <span class="text-bibB {{ $match->team_b_score > $match->team_a_score ? 'underline underline-offset-4' : '' }}">{{ $match->team_b_score }}</span>
                                </span>
                                <span class="text-xs text-pitch-muted leading-relaxed">
                                    @if ($teamNames('A'))<b class="text-bibA">Turuncu:</b> {{ $teamNames('A') }}<br>@endif
                                    @if ($teamNames('B'))<b class="text-bibB">Yeşil:</b> {{ $teamNames('B') }}@endif
                                </span>
                            </div>
                            @if ($goalText || $mvpName)
                                <div class="text-xs text-pitch-muted mt-1">
                                    @if ($goalText)⚽ <b class="text-pitch-ink">{{ $goalText }}</b>@endif
                                    @if ($goalText && $mvpName) &nbsp;·&nbsp; @endif
                                    @if ($mvpName)⭐ Maçın adamı: <b class="text-gold">{{ $mvpName }}</b>@endif
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
