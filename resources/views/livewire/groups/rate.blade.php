@php
    use App\Support\Attributes;
    $tier = fn (float $o) => $o >= 8 ? 'text-gold' : ($o >= 6.5 ? 'text-[#7DE39A]' : ($o >= 5 ? 'text-pitch-ink' : 'text-pitch-muted'));
@endphp

<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-sm text-bibB hover:underline">← {{ $group->name }}</a>
            <h2 class="font-display uppercase tracking-wider text-2xl font-bold mt-1">Oyuncuları Puanla</h2>
            <p class="text-sm text-pitch-muted mt-1">
                Puanların <strong class="text-pitch-ink">anonim</strong> — kimseye kimin ne verdiği gösterilmez.
                İstediğin zaman dönüp güncelleyebilirsin; ortalamalar kadro dengelemesinde kullanılır.
            </p>
        </div>

        <div class="grid lg:grid-cols-[340px,1fr] gap-6 items-start">
            {{-- Oyuncu listesi --}}
            <div class="min-w-0 bg-pitch-surface border border-pitch-line rounded-xl p-4 space-y-1">
                @forelse ($players as $player)
                    <button wire:click="select({{ $player->id }})"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-start transition
                                   {{ $selectedId === $player->id ? 'bg-bibB/10 border border-bibB' : 'border border-transparent hover:bg-pitch-surface2' }}">
                        <x-ovr-badge :player="$player" num-class="text-xl w-10 shrink-0" />
                        
                        <span class="grow min-w-0">
                            <span class="font-semibold block truncate">{{ $player->name }}</span>
                            <span class="block text-xs text-pitch-muted truncate">{{ implode(' · ', array_map(fn ($p) => Attributes::POSITIONS[$p] ?? $p, $player->positions ?? [])) }}</span>
                        </span>
                        
                        @if ($ratedIds->contains($player->id))
                            <span class="text-bibB text-sm shrink-0" title="Puanladın">✓</span>
                        @else
                            <span class="text-pitch-muted text-xs shrink-0">puanlanmadı</span>
                        @endif
                    </button>
                @empty
                    <p class="text-pitch-muted p-3">Puanlayacak başka oyuncu yok.</p>
                @endforelse
            </div>

            {{-- Puanlama formu --}}
            <div class="min-w-0 bg-pitch-surface border border-pitch-line rounded-xl p-4 sm:p-6">
                @if ($selected)
                    <form wire:submit="save" class="space-y-5">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="font-display uppercase tracking-wider text-xl font-semibold truncate min-w-0">{{ $selected->name }}</h3>
                            <div class="text-end shrink-0">
                                <span class="block text-[11px] tracking-[.15em] text-pitch-muted">SENİN VERDİĞİN GENEL</span>
                                <span class="font-display text-3xl font-bold {{ $tier(Attributes::overall($scores, $selected->positions ?? [])) }}">
                                    {{ number_format(Attributes::overall($scores, $selected->positions ?? []), 1) }}
                                </span>
                            </div>
                        </div>

                        @php
                            // Genel özellikler herkes için; pozisyon sırasına göre kaleci/defans/orta saha/forvet bölümleri eklenir
                            $sections = [['GENEL ÖZELLİKLER', Attributes::GENERAL]];
                            foreach ($selected->positions ?? [] as $pos) {
                                if ($pos === 'KL') {
                                    $sections[] = ['KALECİ ÖZELLİKLERİ', Attributes::GK];
                                } elseif (isset(Attributes::SPEC[$pos])) {
                                    $sections[] = [Attributes::POSITIONS[$pos].' ÖZELLİKLERİ', Attributes::SPEC[$pos]];
                                }
                            }
                        @endphp

                        @foreach ($sections as [$sectionTitle, $attrs])
                                <div>
                                    <div class="text-[11px] font-extrabold tracking-[.18em] text-pitch-muted border-t border-dashed border-pitch-line pt-3 mb-3">{{ $sectionTitle }}</div>
                                    <div class="space-y-3">
                                        @foreach ($attrs as $key => $label)
                                            <div class="grid grid-cols-[76px,1fr] sm:grid-cols-[110px,1fr] items-center gap-1.5 sm:gap-3">
                                                
                                                <span class="text-[10px] sm:text-[11px] font-bold tracking-wide text-pitch-muted leading-tight">{{ $label }}</span>
                                                
                                                <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                                                    <button type="button" wire:click="adjust('{{ $key }}', -1)"
                                                            class="w-8 h-8 sm:w-9 sm:h-9 shrink-0 rounded-md bg-pitch-bg border border-pitch-line text-xl font-bold leading-none hover:bg-pitch-surface2 active:scale-95 transition">−</button>
                                                    
                                                    <input type="range" min="1" max="10" step="1"
                                                        wire:model.live="scores.{{ $key }}"
                                                        class="grow min-w-[40px] sm:min-w-0 h-1.5 accent-bibB cursor-pointer">
                                                    
                                                    <button type="button" wire:click="adjust('{{ $key }}', 1)"
                                                            class="w-8 h-8 sm:w-9 sm:h-9 shrink-0 rounded-md bg-pitch-bg border border-pitch-line text-xl font-bold leading-none hover:bg-pitch-surface2 active:scale-95 transition">+</button>
                                                    
                                                    <output class="font-display text-base sm:text-lg font-semibold w-5 sm:w-7 text-end shrink-0">{{ $scores[$key] ?? 5 }}</output>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                        <div class="flex items-center gap-3 pt-2">
                            <x-primary-button>Puanları Kaydet</x-primary-button>
                            @if ($saved)
                                <span class="text-bibB text-sm">Kaydedildi ✓</span>
                            @endif
                        </div>
                    </form>
                @else
                    <div class="text-center text-pitch-muted py-16">
                        <div class="text-4xl mb-3">⭐</div>
                        Soldaki listeden bir oyuncu seç ve özelliklerini 1-10 arası puanla.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>