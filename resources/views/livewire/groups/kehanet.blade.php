@php
    use App\Support\Kehanet as K;
@endphp

<div class="py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <x-group-nav :group="$group" active="kehanet" />

        {{-- Başlık + bakiye --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← {{ $group->name }}</a>
                    <h2 class="font-display uppercase tracking-wider text-2xl font-bold mt-1">🔮 Kehanet</h2>
                </div>
                <div class="text-end shrink-0">
                    <div class="font-display text-3xl font-bold text-bibB">{{ number_format($balance) }}</div>
                    <div class="text-[10px] tracking-[.2em] text-pitch-muted">ÇİM</div>
                    @if ($myStreak['current'] >= 2)
                        <div class="text-[11px] text-gold mt-0.5">🔥 {{ $myStreak['current'] }} maç serisi</div>
                    @elseif ($myStreak['best'] >= 2)
                        <div class="text-[11px] text-pitch-muted mt-0.5">En uzun seri: {{ $myStreak['best'] }}</div>
                    @endif
                </div>
            </div>
            <p class="text-xs text-pitch-muted mt-3">
                Her hafta <strong class="text-pitch-ink">{{ K::WEEKLY_GRANT }} Çim</strong> hesabına yüklenir. Çim tamamen sanaldır — eğlence amaçlıdır, gerçek parayla ilişkisi yoktur.
            </p>
            @if ($notice)
                <p class="mt-3 text-sm text-bibB bg-bibB/10 border border-bibB/30 rounded-md px-3 py-2">{{ $notice }}</p>
            @endif
        </div>

        {{-- Kombine sepeti --}}
        @if ($parlay)
            @php $toplamOran = array_product(array_column($parlay, 'odds')); @endphp
            <div class="bg-pitch-surface border border-bibB/50 rounded-xl p-4 sm:p-6 space-y-3">
                <div class="flex items-baseline justify-between gap-2 flex-wrap">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-bibB">🎰 Kombine Kupon</h3>
                    <span class="text-xs text-pitch-muted">{{ count($parlay) }} tahmin · hepsi tutmalı</span>
                </div>

                <div class="space-y-1.5">
                    @foreach ($parlay as $bacak)
                        <div class="flex items-center justify-between gap-2 bg-pitch-bg border border-pitch-line rounded-lg px-3 py-2 text-sm">
                            <span class="min-w-0 truncate">{{ $bacak['label'] }}</span>
                            <span class="flex items-center gap-2 shrink-0">
                                <span class="font-display font-bold">{{ number_format($bacak['odds'], 2) }}×</span>
                                <button wire:click="toggleParlay({{ $bacak['match_id'] }}, '{{ $bacak['market'] }}', '{{ $bacak['selection'] }}')"
                                        class="text-pitch-muted hover:text-[#FF8A8A]">&times;</button>
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-pitch-line flex-wrap">
                    <span class="text-sm">Toplam oran: <strong class="font-display text-xl text-gold">{{ number_format(min(500, $toplamOran), 2) }}×</strong></span>
                    <div class="flex items-center gap-2">
                        <input type="number" min="{{ K::MIN_STAKE }}" max="{{ K::MAX_STAKE }}" wire:model="parlayStake"
                               class="w-20 text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                        <span class="text-xs text-pitch-muted">Çim → <strong class="text-gold">{{ number_format($parlayStake * min(500, $toplamOran)) }}</strong></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex">
                    <x-primary-button wire:click="placeParlay" class="w-full sm:w-auto"
                            data-confirm="{{ count($parlay) }}'li kombine oynuyorsun. Hepsi tutmalı, iptal edilemez — emin misin?"
                            data-confirm-danger="false">
                        Kombineyi Oyna
                    </x-primary-button>
                    <x-secondary-button wire:click="clearParlay" class="w-full sm:w-auto">Temizle</x-secondary-button>
                </div>
                @if (count($parlay) < K::MIN_LEGS)
                    <p class="text-xs text-gold">En az {{ K::MIN_LEGS }} tahmin gerekli.</p>
                @endif
            </div>
        @endif

        {{-- Aktif kuponların (sonucu beklenenler) --}}
        @php $aktif = $myBets->where('status', 'pending'); @endphp
        @if ($aktif->isNotEmpty())
            <div class="bg-pitch-surface border border-gold/40 rounded-xl p-4 sm:p-6">
                <div class="flex items-baseline justify-between gap-2 mb-3 flex-wrap">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-gold">⏳ Bekleyen Tahminlerin</h3>
                    <span class="text-xs text-pitch-muted">
                        {{ $aktif->count() }} kupon · {{ number_format($aktif->sum('stake')) }} Çim riskte
                    </span>
                </div>

                <div class="space-y-2">
                    @foreach ($aktif as $bet)
                        <div class="flex items-center justify-between gap-3 bg-pitch-bg border border-pitch-line rounded-lg px-3 py-2">
                            <div class="min-w-0">
                                <div class="text-sm min-w-0">
                                    <span class="text-pitch-muted">{{ K::icon($bet->market_key) }} {{ K::label($bet->market_key) }}:</span>
                                    <strong class="text-bibB">{{ $this->selectionText($bet->market_key, $bet->selection) }}</strong>
                                </div>
                                <div class="text-xs text-pitch-muted truncate">
                                    {{ $bet->match?->title }}
                                    @if ($bet->match)
                                        · 📅 {{ $bet->match->starts_at->translatedFormat('d F, l H:i') }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-end shrink-0">
                                <div class="font-display font-bold text-sm">{{ $bet->stake }} → <span class="text-gold">{{ $bet->potentialPayout() }}</span></div>
                                <div class="text-[11px] text-pitch-muted">{{ $bet->odds }}×</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Açık maçlar: kupon yapma --}}
        @forelse ($openMatches as $match)
            @php $squad = $this->squadFor($match); @endphp

            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6 space-y-4">
                <div class="flex items-baseline justify-between gap-2 flex-wrap">
                    <h3 class="font-display uppercase tracking-wider text-lg font-semibold">{{ $match->title }}</h3>
                    <span class="text-xs text-pitch-muted">{{ $match->starts_at->translatedFormat('d F, l H:i') }}</span>
                </div>

                @php $takimlar = $this->teamsFor($match); @endphp

                @if ($takimlar)
                    {{-- Kadro belli: kim hangi takımda --}}
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ([['A', 'Turuncu', 'text-bibA', 'border-bibA/40'], ['B', 'Yeşil', 'text-bibB', 'border-bibB/40']] as [$harf, $ad, $renk, $kenar])
                            <div class="border {{ $kenar }} rounded-lg p-3 min-w-0">
                                <div class="text-[11px] font-bold tracking-[.14em] {{ $renk }} mb-1.5">{{ mb_strtoupper($ad, 'UTF-8') }}</div>
                                <ul class="space-y-1">
                                    @foreach ($takimlar[$harf] as $p)
                                        @php $benMiyim = $p->user_id === auth()->id(); @endphp
                                        <li class="text-xs truncate {{ $benMiyim ? 'text-bibB font-bold' : 'text-pitch-ink' }}">
                                            {{ $p->name }}@if ($benMiyim) <span class="text-[10px] bg-bibB/15 text-bibB rounded px-1">SEN</span>@endif
                                        </li>
                                    @endforeach
                                    @if ($takimlar[$harf]->isEmpty())
                                        <li class="text-xs text-pitch-muted">—</li>
                                    @endif
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @elseif ($squad->isEmpty())
                    <p class="text-sm text-pitch-muted">Kadro henüz belli değil — oyuncu tahminleri kadro kurulunca açılır.</p>
                @else
                    <p class="text-sm text-pitch-muted">Takımlar henüz kurulmadı — gelenler arasından tahmin yapabilirsin.</p>
                @endif

                <div class="space-y-3">
                    @foreach (K::MARKETS as $key => $market)
                        @php
                            $kind = $market['kind'];
                            $secenekler = $kind === 'takim'
                                ? K::teamOptions($key)
                                : ($kind === 'altust'
                                    ? ['under' => $line.' Alt', 'over' => $line.' Üst']
                                    // Kendi hakkında tahmin yapılamaz
                                    : $squad->where('user_id', '!=', auth()->id())->pluck('name', 'id')->all());
                            $anahtar = $match->id.'-'.$key;
                            $mevcut = $myBets->firstWhere(fn ($b) => $b->match_id === $match->id && $b->market_key === $key && $b->status === 'pending');
                        @endphp

                        @if ($kind === 'skor')
                            {{-- Skor tam tahmini: iki sayı girilir, oran anlık hesaplanır --}}
                            @php
                                $sa = (int) ($scorePick[$match->id.'-a'] ?? 0);
                                $sb = (int) ($scorePick[$match->id.'-b'] ?? 0);
                                $skorOran = $odds->odds($match, 'exact_score', "{$sa}-{$sb}");
                            @endphp
                            <div class="border border-pitch-line rounded-lg p-3">
                                <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                                    <span class="text-sm font-semibold">{{ $market['icon'] }} {{ $market['name'] }}</span>
                                    @if ($mevcut)
                                        <span class="text-[11px] text-gold">✓ {{ $mevcut->selection }} · {{ $mevcut->stake }} Çim @ {{ $mevcut->odds }}×</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-center gap-2 flex-wrap">
                                    <span class="text-xs font-bold text-bibA">Turuncu</span>
                                    <input type="number" min="0" max="20" wire:model.live="scorePick.{{ $match->id }}-a"
                                           class="w-14 text-center text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                    <span class="text-pitch-muted">:</span>
                                    <input type="number" min="0" max="20" wire:model.live="scorePick.{{ $match->id }}-b"
                                           class="w-14 text-center text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                    <span class="text-xs font-bold text-bibB">Yeşil</span>
                                    <span class="font-display font-bold text-gold ms-2">{{ number_format($skorOran, 2) }}×</span>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <input type="number" min="{{ K::MIN_STAKE }}" max="{{ K::MAX_STAKE }}" placeholder="20"
                                           wire:model="stake.{{ $anahtar }}"
                                           class="w-24 text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                    <span class="text-xs text-pitch-muted">Çim</span>
                                    <x-secondary-button wire:click="betScore({{ $match->id }})" class="ms-auto"
                                            data-confirm="{{ $sa }}-{{ $sb }} skorunu tahmin ediyorsun. İptal edilemez — emin misin?"
                                            data-confirm-danger="false">
                                        Kuponu Yap
                                    </x-secondary-button>
                                </div>
                            </div>
                        @elseif ($secenekler)
                            <div class="border border-pitch-line rounded-lg p-3">
                                <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                                    <span class="text-sm font-semibold">{{ $market['icon'] }} {{ $market['name'] }}</span>
                                    @if ($mevcut)
                                        <span class="text-[11px] text-gold">
                                            ✓ {{ $this->selectionText($mevcut->market_key, $mevcut->selection) }} · {{ $mevcut->stake }} Çim @ {{ $mevcut->odds }}×
                                        </span>
                                    @endif
                                </div>

                                @php
                                    $nabiz = $pulse[$match->id][$key] ?? [];
                                    $nabizToplam = array_sum($nabiz);
                                @endphp

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach ($secenekler as $deger => $etiket)
                                        @php
                                            $o = $odds->odds($match, $key, (string) $deger);
                                            $oran = $nabizToplam > 0 ? round(($nabiz[(string) $deger] ?? 0) / $nabizToplam * 100) : null;
                                            $sepette = collect($parlay)->contains('key', $anahtar);
                                        @endphp
                                        <div class="relative">
                                            <button type="button" wire:click="$set('selection.{{ $anahtar }}', '{{ $deger }}')"
                                                    class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-md border text-xs transition
                                                           {{ (string) ($selection[$anahtar] ?? '') === (string) $deger
                                                              ? 'border-bibB bg-bibB/10 text-bibB font-semibold'
                                                              : 'border-pitch-line hover:bg-pitch-surface2' }}">
                                                <span class="truncate">
                                                    {{ $etiket }}
                                                    @if ($oran !== null && $oran > 0)
                                                        <span class="text-[10px] text-pitch-muted">%{{ $oran }}</span>
                                                    @endif
                                                </span>
                                                <span class="font-display font-bold shrink-0">{{ number_format($o, 2) }}×</span>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($nabizToplam > 0)
                                    <p class="text-[10px] text-pitch-muted mt-1">📊 Grubun nabzı — {{ $nabizToplam }} kişi tahmin yaptı</p>
                                @endif

                                <div class="flex items-center gap-2 mt-2">
                                    <input type="number" min="{{ K::MIN_STAKE }}" max="{{ K::MAX_STAKE }}" placeholder="20"
                                           wire:model="stake.{{ $anahtar }}"
                                           class="w-24 text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                    <span class="text-xs text-pitch-muted">Çim</span>
                                    @if (($selection[$anahtar] ?? '') !== '')
                                        <button type="button"
                                                wire:click="toggleParlay({{ $match->id }}, '{{ $key }}', '{{ $selection[$anahtar] }}')"
                                                class="text-xs px-2 py-2 rounded-md border transition
                                                       {{ collect($parlay)->contains('key', $anahtar)
                                                          ? 'border-bibB bg-bibB/10 text-bibB' : 'border-pitch-line hover:bg-pitch-surface2' }}"
                                                title="Kombineye ekle">🎰</button>
                                    @endif
                                    <x-secondary-button wire:click="bet({{ $match->id }}, '{{ $key }}')" class="ms-auto"
                                            data-confirm="{{ $market['name'] }} tahminini yapıyorsun. Kupon yapıldıktan sonra {{ $mevcut ? 'değiştirilebilir ama iptal edilemez' : 'iptal edilemez' }} — emin misin?"
                                            data-confirm-danger="false">
                                        {{ $mevcut ? 'Kuponu Değiştir' : 'Kuponu Yap' }}
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 text-center text-pitch-muted">
                <div class="text-4xl mb-2">🔮</div>
                Kupon yapılabilecek maç yok — yaklaşan maç açılınca burada belirir.
            </div>
        @endforelse

        {{-- Başkan: maç olayları girişi --}}
        @if ($isAdmin && $pendingMatches->isNotEmpty())
            <div class="bg-pitch-surface border border-gold/40 rounded-xl p-4 sm:p-6 space-y-4">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold text-gold">🎬 Maç Olayları <span class="text-xs text-pitch-muted font-normal tracking-normal">(başkan)</span></h3>
                <p class="text-xs text-pitch-muted">Bu olayları işaretleyince ilgili kuponlar otomatik sonuçlanır. Dokunmadığın olay beklemede kalır.</p>

                @foreach ($pendingMatches as $match)
                    @php $squad = $this->squadFor($match); @endphp
                    <div class="border border-pitch-line rounded-lg p-3 space-y-2">
                        <div class="text-sm font-semibold">{{ $match->title }} <span class="text-xs text-pitch-muted">· {{ $match->starts_at->translatedFormat('d F') }}</span></div>

                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach (K::EVENTS as $key => $olay)
                                @php $kayitli = $match->events->firstWhere('event_key', $key); @endphp
                                <label class="flex items-center justify-between gap-2 bg-pitch-bg border border-pitch-line rounded-md px-3 py-2">
                                    <span class="text-xs min-w-0" title="{{ $olay['hint'] }}">{{ $olay['icon'] }} {{ $olay['name'] }}</span>
                                    <select wire:model="eventPick.{{ $match->id }}-{{ $key }}"
                                            class="w-32 shrink-0 text-xs bg-pitch-surface border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                        <option value="">{{ $kayitli ? ($kayitli->player?->name ?? 'Kimse') : '— seç —' }}</option>
                                        <option value="yok">Kimse</option>
                                        @foreach ($squad as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endforeach
                        </div>

                        <x-primary-button wire:click="saveEvents({{ $match->id }})" class="w-full sm:w-auto">
                            Kaydet ve Kuponları Sonuçlandır
                        </x-primary-button>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Ayın Kâhini --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
            <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-1">👑 Ayın Kâhini</h3>
            <p class="text-xs text-pitch-muted mb-3">{{ now()->translatedFormat('F Y') }} — net kazanç sıralaması</p>

            @if ($leaders->isEmpty())
                <p class="text-sm text-pitch-muted">Bu ay henüz sonuçlanmış kupon yok.</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($leaders as $i => $lider)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-display font-bold w-6 text-center {{ $i === 0 ? 'text-gold' : 'text-pitch-muted' }}">{{ $i === 0 ? '👑' : ($i + 1).'.' }}</span>
                            <span class="font-semibold min-w-0 truncate">{{ $lider->name }}</span>
                            @php $seri = $streaks[$lider->user_id] ?? ['current' => 0, 'best' => 0]; @endphp
                            @if ($seri['current'] >= 2)
                                <span class="text-[11px] text-gold shrink-0">🔥{{ $seri['current'] }}</span>
                            @endif
                            <span class="text-xs text-pitch-muted ms-auto shrink-0">{{ $lider->tuttu }}/{{ $lider->toplam }}</span>
                            <span class="font-display font-bold w-20 text-end shrink-0 {{ $lider->net > 0 ? 'text-[#7DE39A]' : ($lider->net < 0 ? 'text-[#FF8A8A]' : 'text-pitch-muted') }}">
                                {{ $lider->net > 0 ? '+' : '' }}{{ number_format($lider->net) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Kombine kuponlarım --}}
        @if ($mySlips->isNotEmpty())
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">🎰 Kombine Kuponlarım</h3>
                <div class="space-y-2">
                    @foreach ($mySlips as $slip)
                        @php
                            $renk = match ($slip->status) {
                                'won' => 'border-[#7DE39A]/40', 'lost' => 'border-[#6c3030]',
                                'void' => 'border-pitch-line', default => 'border-gold/40',
                            };
                            $durum = match ($slip->status) {
                                'won' => '✓ Kazandı', 'lost' => '✕ Kaybetti',
                                'void' => '↩ İade', default => '⏳ Bekliyor',
                            };
                        @endphp
                        <div class="border {{ $renk }} rounded-lg p-3">
                            <div class="flex items-center justify-between gap-2 mb-1.5 flex-wrap">
                                <span class="text-sm font-semibold">{{ $slip->legs->count() }}'li kombine · {{ $slip->total_odds }}×</span>
                                <span class="text-xs {{ $slip->status === 'won' ? 'text-[#7DE39A]' : ($slip->status === 'lost' ? 'text-[#FF8A8A]' : 'text-gold') }}">
                                    {{ $durum }}
                                    @if ($slip->status === 'won') +{{ number_format($slip->payout - $slip->stake) }} @endif
                                </span>
                            </div>
                            <div class="space-y-0.5">
                                @foreach ($slip->legs as $bacak)
                                    @php $isaret = match ($bacak->status) { 'won' => '✓', 'lost' => '✕', 'void' => '↩', default => '·' }; @endphp
                                    <div class="text-xs text-pitch-muted truncate">
                                        <span class="{{ $bacak->status === 'won' ? 'text-[#7DE39A]' : ($bacak->status === 'lost' ? 'text-[#FF8A8A]' : '') }}">{{ $isaret }}</span>
                                        {{ K::label($bacak->market_key) }}: <strong class="text-pitch-ink">{{ $this->selectionText($bacak->market_key, $bacak->selection) }}</strong>
                                        <span class="text-pitch-muted/70">{{ $bacak->odds }}×</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="text-xs text-pitch-muted mt-1.5">{{ $slip->stake }} Çim → {{ number_format($slip->potentialPayout()) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Kuponlarım --}}
        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
            <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">🎫 Kuponlarım</h3>

            @if ($myBets->isEmpty())
                <p class="text-sm text-pitch-muted">Henüz kupon yapmadın.</p>
            @else
                <div class="divide-y divide-pitch-line">
                    @foreach ($myBets as $bet)
                        @php
                            $renk = match ($bet->status) {
                                'won' => 'text-[#7DE39A]', 'lost' => 'text-[#FF8A8A]',
                                'void' => 'text-pitch-muted', default => 'text-gold',
                            };
                            $durum = match ($bet->status) {
                                'won' => '✓ Kazandı', 'lost' => '✕ Kaybetti',
                                'void' => '↩ İade', default => '⏳ Bekliyor',
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <div class="truncate">
                                    <span class="text-pitch-muted">{{ K::icon($bet->market_key) }} {{ K::label($bet->market_key) }}:</span>
                                    <strong>{{ $this->selectionText($bet->market_key, $bet->selection) }}</strong>
                                </div>
                                <div class="text-xs text-pitch-muted truncate">{{ $bet->match?->title }} · {{ $bet->stake }} Çim @ {{ $bet->odds }}×</div>
                            </div>
                            <div class="text-end shrink-0">
                                <div class="text-xs {{ $renk }}">{{ $durum }}</div>
                                @if ($bet->status === 'won')
                                    <div class="font-display font-bold text-[#7DE39A]">+{{ $bet->payout - $bet->stake }}</div>
                                @elseif ($bet->status === 'lost')
                                    <div class="font-display font-bold text-[#FF8A8A]">−{{ $bet->stake }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Çim hareket geçmişi --}}
        @if ($transactions->isNotEmpty())
            <div class="bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-3">💸 Çim Hareketleri</h3>
                <div class="divide-y divide-pitch-line">
                    @foreach ($transactions as $hareket)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div class="min-w-0">
                                <div class="truncate">{{ \App\Models\CimTransaction::LABELS[$hareket->type] ?? $hareket->type }}</div>
                                <div class="text-xs text-pitch-muted truncate">
                                    {{ $hareket->description }} · {{ $hareket->created_at->translatedFormat('d M H:i') }}
                                </div>
                            </div>
                            <div class="text-end shrink-0">
                                <div class="font-display font-bold {{ $hareket->amount > 0 ? 'text-[#7DE39A]' : 'text-[#FF8A8A]' }}">
                                    {{ $hareket->amount > 0 ? '+' : '' }}{{ number_format($hareket->amount) }}
                                </div>
                                <div class="text-[10px] text-pitch-muted">{{ number_format($hareket->balance_after) }} Çim</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
