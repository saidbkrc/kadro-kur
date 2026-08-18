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
                </div>
            </div>
            <p class="text-xs text-pitch-muted mt-3">
                Her hafta <strong class="text-pitch-ink">{{ K::WEEKLY_GRANT }} Çim</strong> hesabına yüklenir. Çim tamamen sanaldır — eğlence amaçlıdır, gerçek parayla ilişkisi yoktur.
            </p>
            @if ($notice)
                <p class="mt-3 text-sm text-bibB bg-bibB/10 border border-bibB/30 rounded-md px-3 py-2">{{ $notice }}</p>
            @endif
        </div>

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
                                <div class="text-xs text-pitch-muted truncate">{{ $bet->match?->title }}</div>
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
                                    : $squad->pluck('name', 'id')->all());
                            $anahtar = $match->id.'-'.$key;
                            $mevcut = $myBets->firstWhere(fn ($b) => $b->match_id === $match->id && $b->market_key === $key && $b->status === 'pending');
                        @endphp

                        @if ($secenekler)
                            <div class="border border-pitch-line rounded-lg p-3">
                                <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                                    <span class="text-sm font-semibold">{{ $market['icon'] }} {{ $market['name'] }}</span>
                                    @if ($mevcut)
                                        <span class="text-[11px] text-gold">
                                            Kuponun: {{ $mevcut->stake }} Çim @ {{ $mevcut->odds }}× → {{ $mevcut->potentialPayout() }}
                                            <button wire:click="cancelBet({{ $mevcut->id }})" class="ms-1 underline hover:text-pitch-ink">iptal</button>
                                        </span>
                                    @endif
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach ($secenekler as $deger => $etiket)
                                        @php $o = $odds->odds($match, $key, (string) $deger); @endphp
                                        <button type="button" wire:click="$set('selection.{{ $anahtar }}', '{{ $deger }}')"
                                                class="flex items-center justify-between gap-2 px-3 py-2 rounded-md border text-xs transition
                                                       {{ (string) ($selection[$anahtar] ?? '') === (string) $deger
                                                          ? 'border-bibB bg-bibB/10 text-bibB font-semibold'
                                                          : 'border-pitch-line hover:bg-pitch-surface2' }}">
                                            <span class="truncate">{{ $etiket }}</span>
                                            <span class="font-display font-bold shrink-0">{{ number_format($o, 2) }}×</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="flex items-center gap-2 mt-2">
                                    <input type="number" min="{{ K::MIN_STAKE }}" max="{{ K::MAX_STAKE }}" placeholder="20"
                                           wire:model="stake.{{ $anahtar }}"
                                           class="w-24 text-sm bg-pitch-bg border-pitch-line text-pitch-ink rounded-md focus:border-bibB focus:ring-bibB/40">
                                    <span class="text-xs text-pitch-muted">Çim</span>
                                    <x-secondary-button wire:click="bet({{ $match->id }}, '{{ $key }}')" class="ms-auto">
                                        Kuponu Yap
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
                            <span class="text-xs text-pitch-muted ms-auto shrink-0">{{ $lider->tuttu }}/{{ $lider->toplam }}</span>
                            <span class="font-display font-bold w-20 text-end shrink-0 {{ $lider->net > 0 ? 'text-[#7DE39A]' : ($lider->net < 0 ? 'text-[#FF8A8A]' : 'text-pitch-muted') }}">
                                {{ $lider->net > 0 ? '+' : '' }}{{ number_format($lider->net) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

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

    </div>
</div>
