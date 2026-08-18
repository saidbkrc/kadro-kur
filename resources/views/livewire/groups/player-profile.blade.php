<div class="py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <x-group-nav :group="$group" :active="$player->user_id === auth()->id() ? 'profile' : null" />

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

        @if ($bestPartner)
            @php $partner = $bestPartner['a']->id === $player->id ? $bestPartner['b'] : $bestPartner['a']; @endphp
            <div class="bg-pitch-surface border border-pitch-line rounded-xl px-4 py-3 text-sm flex items-center gap-2 flex-wrap">
                <span>🧪 En uyumlu ortağı:</span>
                <a href="{{ route('groups.player', [$group, $partner]) }}" wire:navigate class="font-semibold text-bibB hover:underline">{{ $partner->name }}</a>
                <span class="text-pitch-muted">— birlikte %{{ $bestPartner['rate'] }} kazanma ({{ $bestPartner['together'] }} maç)</span>
            </div>
        @endif

        {{-- Kafa kafaya karşılaştırma --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">⚔️ Kafa Kafaya</h3>
                <select wire:model.live="compareId"
                        class="w-full sm:w-64 bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40">
                    <option value="">Rakip seç…</option>
                    @foreach ($otherPlayers as $other)
                        <option value="{{ $other->id }}">{{ $other->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($compare && $compareStats !== null)
                @php
                    $fmt = fn ($v) => is_float($v) ? number_format($v, 1) : $v;
                    $rate = fn (array $s) => $s['played'] > 0 ? round($s['win'] / $s['played'] * 100) : 0;
                    // [etiket, sol değer, sağ değer, gösterim soneki]
                    $rows = [
                        ['GENEL PUAN', $player->overallIsPublic() ? $player->displayRating() : null, $compare->overallIsPublic() ? $compare->displayRating() : null, ''],
                        ['MAÇ', $stats['played'], $compareStats['played'], ''],
                        ['GALİBİYET', $stats['win'], $compareStats['win'], ''],
                        ['KAZANMA', $rate($stats), $rate($compareStats), '%'],
                        ['GOL', $stats['goals'], $compareStats['goals'], ''],
                        ['MVP', $stats['mvp'], $compareStats['mvp'], ''],
                        ['EN UZUN SERİ', $stats['win_streak'], $compareStats['win_streak'], ''],
                        ['ROZET', $myEarned, $compareEarned, ''],
                    ];
                @endphp

                <div class="grid grid-cols-[1fr,auto,1fr] items-center gap-2 pb-3 border-b border-pitch-line">
                    <div class="flex flex-col items-center gap-1 min-w-0">
                        <x-ovr-badge :player="$player" numClass="text-3xl w-14" />
                        <span class="font-display uppercase tracking-wide text-sm font-bold text-center truncate w-full">{{ $player->name }}</span>
                    </div>
                    <span class="font-display text-xl font-bold text-pitch-muted">VS</span>
                    <div class="flex flex-col items-center gap-1 min-w-0">
                        <x-ovr-badge :player="$compare" numClass="text-3xl w-14" />
                        <a href="{{ route('groups.player', [$group, $compare]) }}" wire:navigate
                           class="font-display uppercase tracking-wide text-sm font-bold text-center truncate w-full hover:text-bibB">{{ $compare->name }}</a>
                    </div>
                </div>

                <div class="divide-y divide-pitch-line/60">
                    @foreach ($rows as [$label, $left, $right, $suffix])
                        @php
                            $leftWins = $left !== null && ($right === null || $left > $right);
                            $rightWins = $right !== null && ($left === null || $right > $left);
                        @endphp
                        <div class="grid grid-cols-[1fr,auto,1fr] items-center gap-2 py-2 text-center">
                            <span class="font-display text-lg font-bold {{ $leftWins && !$rightWins ? 'text-bibB' : 'text-pitch-ink' }}">
                                {{ $left === null ? '?' : $fmt($left).$suffix }}{{ $leftWins && !$rightWins ? ' ◂' : '' }}
                            </span>
                            <span class="text-[10px] tracking-[.15em] text-pitch-muted w-28">{{ $label }}</span>
                            <span class="font-display text-lg font-bold {{ $rightWins && !$leftWins ? 'text-bibB' : 'text-pitch-ink' }}">
                                {{ $rightWins && !$leftWins ? '▸ ' : '' }}{{ $right === null ? '?' : $fmt($right).$suffix }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-pitch-muted">Gruptan bir rakip seç — istatistikler FIFA tarzı yan yana kıyaslanır. 🎮</p>
            @endif
        </div>

        {{-- FIFA tarzı oyuncu kartı --}}
        @php
            $ovrPublic = $player->overallIsPublic() && ! $player->isGuest();
            $ovr = $ovrPublic ? $player->displayRating() : ($player->isGuest() ? $player->overall() : null);
            $ovrTier = $ovr === null ? 'text-pitch-muted' : ($ovr >= 8 ? 'text-gold' : ($ovr >= 6.5 ? 'text-[#7DE39A]' : 'text-pitch-ink'));
        @endphp
        <div>
            <div class="relative w-64 mx-auto rounded-2xl border border-gold/50 bg-gradient-to-b from-pitch-surface2 to-pitch-surface p-5 shadow-[0_0_35px_rgba(255,200,61,.10)]">
                <div class="flex items-start justify-between gap-3">
                    <div class="text-center shrink-0">
                        <div class="font-display text-4xl font-extrabold leading-none {{ $ovrTier }}">{{ $ovr !== null ? number_format($ovr, 1) : '?' }}</div>
                        <div class="text-[9px] tracking-[.2em] text-pitch-muted mt-0.5">GENEL</div>
                        <div class="mt-1.5 text-xs font-bold text-pitch-muted">
                            {{ ($player->positions[0] ?? '—') }} · {{ $player->footBadge() }}
                        </div>
                    </div>
                    <div class="w-24 h-24 rounded-xl overflow-hidden border border-pitch-line bg-pitch-bg flex items-center justify-center shrink-0">
                        @if ($player->photoUrl())
                            <img src="{{ $player->photoUrl() }}" alt="{{ $player->name }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-4xl opacity-40">👤</span>
                        @endif
                    </div>
                </div>

                <div class="mt-3 pt-2 border-t border-gold/30 text-center font-display uppercase tracking-wider text-lg font-bold truncate">
                    {{ $player->name }}@if ($player->shirt_number) <span class="text-pitch-muted">#{{ $player->shirt_number }}</span>@endif
                </div>

                <div class="mt-2 grid grid-cols-2 gap-x-8 gap-y-1.5 px-3">
                    @foreach ($player->cardStats() as $label => $value)
                        <div class="flex items-baseline justify-between">
                            <span class="text-[10px] tracking-[.15em] text-pitch-muted">{{ $label }}</span>
                            <span class="font-display text-lg font-bold">{{ $ovrPublic ? number_format($value, 1) : '?' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($player->user_id === auth()->id())
                <label class="block w-64 mx-auto mt-2 text-center text-xs text-bibB hover:underline cursor-pointer">
                    📷 Kart fotoğrafını değiştir
                    <input type="file" wire:model="photo" accept="image/*" class="hidden">
                </label>
                <div wire:loading wire:target="photo" class="mt-1 text-center text-xs text-pitch-muted">Yükleniyor…</div>
                <x-input-error :messages="$errors->get('photo')" class="mt-1 text-center" />
            @endif

            {{-- Gelişim grafiği: maç maç performans puanı --}}
            @if ($formHistory->count() >= 2)
                <div class="w-full max-w-md mx-auto mt-5 bg-pitch-surface border border-pitch-line rounded-xl p-4">
                    <x-form-chart :history="$formHistory" />
                    <p class="text-[10px] text-pitch-muted mt-2 leading-snug">
                        Maç sonrası takım arkadaşlarının verdiği puanların maç başına ortalaması. Son 5 maçın ortalaması genel puanına %20 oranında yansır.
                    </p>
                </div>
            @endif
        </div>

        {{-- Nitelikler (takım arkadaşı onayları) --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold">🏷️ Nitelikler</h3>
                @if ($player->user_id !== auth()->id())
                    <x-secondary-button wire:click="openTraitPicker" class="w-full sm:w-auto">
                        {{ $showTraitPicker ? 'Kapat' : '➕ Nitelik Onayla' }}
                    </x-secondary-button>
                @endif
            </div>

            @if ($traitCounts->isEmpty())
                <p class="text-sm text-pitch-muted">
                    Henüz onaylanmış nitelik yok{{ $player->user_id !== auth()->id() ? ' — ilk onayı sen ver!' : ' — takım arkadaşların onayladıkça burada birikecek.' }}
                </p>
            @else
                <div x-data="{ info: null }">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($traitCounts as $key => $count)
                            @php $trait = \App\Support\PlayerTraits::ALL[$key] ?? null; @endphp
                            @if ($trait)
                                <button type="button"
                                        @click="info = info === @js($trait['icon'].' '.$trait['name'].': '.$trait['desc']) ? null : @js($trait['icon'].' '.$trait['name'].': '.$trait['desc'])"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm font-semibold transition active:scale-95
                                               {{ $loop->index < 3 ? 'border-gold/50 bg-gold/10 text-gold' : 'border-pitch-line bg-pitch-bg text-pitch-ink' }}">
                                    {{ $trait['icon'] }} {{ $trait['name'] }}
                                    <span class="{{ $loop->index < 3 ? 'text-gold/80' : 'text-pitch-muted' }} font-normal">×{{ $count }}</span>
                                    @if ($myTraits->contains($key))<span class="text-bibB">✓</span>@endif
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <p x-show="info" x-cloak x-text="info" @click.outside="info = null"
                       class="mt-2 text-xs text-pitch-muted bg-pitch-bg border border-pitch-line rounded-md px-3 py-2"></p>
                </div>
            @endif

            {{-- Takılmalar: ancak eşiği geçenler görünür --}}
            @if ($negativeCounts->isNotEmpty())
                <div x-data="{ info: null }" class="pt-3 border-t border-pitch-line">
                    <div class="text-[11px] tracking-[.14em] text-pitch-muted mb-2">😅 TAKILMALAR</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($negativeCounts as $key => $count)
                            @php $trait = \App\Support\PlayerTraits::ALL[$key] ?? null; @endphp
                            @if ($trait)
                                <button type="button"
                                        @click="info = info === @js($trait['icon'].' '.$trait['name'].': '.$trait['desc']) ? null : @js($trait['icon'].' '.$trait['name'].': '.$trait['desc'])"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-[#6c3030] bg-red-900/15 text-[#ffb3b3] text-sm font-semibold transition active:scale-95">
                                    {{ $trait['icon'] }} {{ $trait['name'] }}
                                    <span class="text-[#ffb3b3]/70 font-normal">×{{ $count }}</span>
                                    @if ($myTraits->contains($key))<span class="text-bibB">✓</span>@endif
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <p x-show="info" x-cloak x-text="info" @click.outside="info = null"
                       class="mt-2 text-xs text-pitch-muted bg-pitch-bg border border-pitch-line rounded-md px-3 py-2"></p>
                </div>
            @endif

            @if ($showTraitPicker && $player->user_id !== auth()->id())
                <div class="border-t border-pitch-line pt-3 space-y-4">
                    @php
                        $secPozitif = collect($selectedTraits)->reject(fn ($k) => \App\Support\PlayerTraits::isNegative($k))->count();
                        $secNegatif = collect($selectedTraits)->filter(fn ($k) => \App\Support\PlayerTraits::isNegative($k))->count();
                    @endphp

                    <p class="text-xs text-pitch-muted">
                        En iyi bildiği {{ \App\Support\PlayerTraits::MAX_PER_ENDORSER }} şeyi seç ({{ $secPozitif }}/{{ \App\Support\PlayerTraits::MAX_PER_ENDORSER }}), sonra <strong class="text-pitch-ink">Kaydet</strong>'e bas. Onaylar anonimdir, sadece sayı görünür.
                    </p>

                    @foreach (\App\Support\PlayerTraits::grouped('positive') as $cat => $traits)
                        <div>
                            <div class="text-[11px] tracking-[.14em] text-pitch-muted mb-2">{{ mb_strtoupper($cat, 'UTF-8') }}</div>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($traits as $key => $trait)
                                    @php
                                        $secili = in_array($key, $selectedTraits, true);
                                        $full = ! $secili && count($selectedTraits) >= \App\Support\PlayerTraits::MAX_PER_ENDORSER;
                                    @endphp
                                    <button type="button" wire:click="toggleTraitSelection('{{ $key }}')"
                                            class="text-start px-3 py-2 rounded-md border transition
                                                   {{ $secili ? 'border-bibB bg-bibB/10' : ($full ? 'border-pitch-line opacity-40' : 'border-pitch-line hover:bg-pitch-surface2') }}">
                                        <span class="block text-xs {{ $secili ? 'text-bibB font-semibold' : 'text-pitch-ink font-medium' }}">{{ $trait['icon'] }} {{ $trait['name'] }}{{ $secili ? ' ✓' : '' }}</span>
                                        <span class="block text-[10px] leading-snug text-pitch-muted mt-0.5">{{ $trait['desc'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    {{-- Şakacı takılmalar: ayrı ve daha kısıtlı --}}
                    <div class="pt-3 border-t border-dashed border-pitch-line">
                        <div class="text-[11px] tracking-[.14em] text-[#ffb3b3] mb-1">😅 TAKILMALAR ({{ $secNegatif }}/{{ \App\Support\PlayerTraits::MAX_NEGATIVE_PER_ENDORSER }})</div>
                        <p class="text-xs text-pitch-muted mb-2">
                            Dostça takılmalar — en fazla {{ \App\Support\PlayerTraits::MAX_NEGATIVE_PER_ENDORSER }} tane. Bir takılma ancak <strong class="text-pitch-ink">{{ \App\Support\PlayerTraits::MIN_NEGATIVE_VISIBLE }} kişi</strong> aynı şeyi seçerse profilde görünür, bildirim gitmez.
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (\App\Support\PlayerTraits::grouped('negative') as $traits)
                                @foreach ($traits as $key => $trait)
                                    @php
                                        $secili = in_array($key, $selectedTraits, true);
                                        $full = ! $secili && $secNegatif >= \App\Support\PlayerTraits::MAX_NEGATIVE_PER_ENDORSER;
                                    @endphp
                                    <button type="button" wire:click="toggleTraitSelection('{{ $key }}')"
                                            class="text-start px-3 py-2 rounded-md border transition
                                                   {{ $secili ? 'border-[#6c3030] bg-red-900/15' : ($full ? 'border-pitch-line opacity-40' : 'border-pitch-line hover:bg-pitch-surface2') }}">
                                        <span class="block text-xs {{ $secili ? 'text-[#ffb3b3] font-semibold' : 'text-pitch-ink font-medium' }}">{{ $trait['icon'] }} {{ $trait['name'] }}{{ $secili ? ' ✓' : '' }}</span>
                                        <span class="block text-[10px] leading-snug text-pitch-muted mt-0.5">{{ $trait['desc'] }}</span>
                                    </button>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-1">
                        <x-primary-button type="button" wire:click="saveTraits" class="w-full sm:w-auto">Kaydet</x-primary-button>
                        @if ($traitNotice)
                            <span class="text-sm {{ str_starts_with($traitNotice, '✓') ? 'text-bibB' : 'text-gold' }} text-center sm:text-start">{{ $traitNotice }}</span>
                        @endif
                    </div>
                </div>
            @endif
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
