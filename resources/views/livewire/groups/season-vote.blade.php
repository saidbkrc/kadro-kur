<div class="py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-group-nav :group="$group" />

        <div>
            <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← {{ $group->name }}</a>
            <h2 class="font-display uppercase tracking-wider text-2xl font-bold mt-1">🗳️ Sezon Oylaması</h2>
            <p class="text-sm text-pitch-muted mt-1">
                Sezon bitince oyuncular sezonu kendi aralarında oylar — istatistiğin ölçemediklerini.
                Oylar <strong class="text-pitch-ink">anonimdir</strong>, oylama kapanana kadar değiştirebilirsin.
            </p>
        </div>

        @if ($notice)
            <p class="text-sm text-bibB bg-bibB/10 border border-bibB/30 rounded-md px-3 py-2">{{ $notice }}</p>
        @endif

        @if ($open)
            @php $verilen = count($myVotes); $toplamKat = count($categories); @endphp
            <div class="bg-pitch-surface border border-gold/40 rounded-xl p-4 sm:p-6">
                <div class="flex items-baseline justify-between gap-2 flex-wrap">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-gold">{{ $open->name() }}</h3>
                    <span class="text-xs text-pitch-muted">
                        Kapanış: <strong class="text-pitch-ink">{{ $open->votingClosesAt()->translatedFormat('j F, H:i') }}</strong>
                        · {{ $voterCount }} kişi oy verdi
                    </span>
                </div>

                @if ($canVote)
                    <p class="text-xs text-pitch-muted mt-2">
                        {{ $verilen }}/{{ $toplamKat }} kategoride oy verdin.
                        Dördünde de oy verirsen <strong class="text-gold">+{{ \App\Services\CimRewards::AWARDS['season_vote_cast']['amount'] }} Çim</strong>;
                        her kategorinin kazananı <strong class="text-gold">+{{ \App\Services\CimRewards::AWARDS['season_vote_win']['amount'] }} Çim</strong> alır.
                    </p>
                @else
                    <p class="text-sm text-pitch-muted mt-2">Bu sezon maça çıkmadığın için oy veremezsin — sonuçları kapanışta burada görebilirsin.</p>
                @endif
            </div>

            @if ($canVote)
                @if ($candidates->isEmpty())
                    <p class="text-sm text-pitch-muted">Bu sezon en az {{ \App\Services\SeasonVoting::MIN_MATCHES_CANDIDATE }} maça çıkan aday yok.</p>
                @endif

                @foreach ($categories as $kat => $k)
                    @php $secim = $myVotes[$kat] ?? null; @endphp
                    <div class="bg-pitch-surface border {{ $secim ? 'border-bibB/50' : 'border-pitch-line' }} rounded-xl p-4 sm:p-6">
                        <div class="flex items-baseline justify-between gap-2 flex-wrap mb-3">
                            <div>
                                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">{{ $k['icon'] }} {{ $k['name'] }}</h3>
                                <p class="text-xs text-pitch-muted">{{ $k['hint'] }}</p>
                            </div>
                            @if ($secim)
                                <span class="text-xs text-bibB font-semibold">✓ Oy verildi</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach ($candidates as $aday)
                                @continue($aday->id === $myPlayerId) {{-- kendine oy yok --}}
                                <button type="button" wire:click="vote('{{ $kat }}', {{ $aday->id }})"
                                        class="px-3 py-2 rounded-md border text-sm transition truncate
                                               {{ $secim === $aday->id ? 'border-bibB bg-bibB/10 text-bibB font-semibold' : 'border-pitch-line hover:bg-pitch-surface2' }}">
                                    <span class="{{ $secim === $aday->id ? '' : $aday->nameColorClass() }}">{{ $aday->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        @else
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                <p class="text-sm text-pitch-muted">
                    Şu an açık oylama yok. Sıradaki oylama <strong class="text-pitch-ink">{{ \App\Support\Season::current()->name() }}</strong>
                    bitince, <strong class="text-pitch-ink">{{ $nextOpens->translatedFormat('j F') }}</strong> tarihinde açılır ve
                    {{ \App\Support\Season::VOTING_DAYS }} gün sürer.
                </p>
            </div>
        @endif

        {{-- Son kapanan oylamanın sonuçları --}}
        @if ($resultsSeason)
            <div class="bg-pitch-surface border border-gold/40 rounded-xl p-4 sm:p-6">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-gold mb-3">🏆 {{ $resultsSeason->name() }} — Oyuncuların Seçimi</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($categories as $kat => $k)
                        @php $r = $results[$kat] ?? null; @endphp
                        <div class="rounded-lg border border-pitch-line bg-pitch-bg px-3 py-3">
                            <div class="text-[11px] tracking-[.14em] text-pitch-muted">{{ $k['icon'] }} {{ mb_strtoupper($k['name'], 'UTF-8') }}</div>
                            @if ($r)
                                <div class="text-sm font-semibold mt-1">
                                    @foreach ($r['winners'] as $w)
                                        <a href="{{ route('groups.player', [$group, $w]) }}" wire:navigate class="hover:underline {{ $w->nameColorClass() }}">{{ $w->name }}</a>@if (! $loop->last), @endif
                                    @endforeach
                                </div>
                                <div class="text-xs text-gold font-display">{{ $r['votes'] }} oy</div>
                            @else
                                <div class="text-sm text-pitch-muted mt-1">Oy verilmedi</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
