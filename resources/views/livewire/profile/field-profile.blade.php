@php
    use App\Support\Attributes;
    $tier = fn (float $o) => $o >= 8 ? 'text-gold' : ($o >= 6.5 ? 'text-[#7DE39A]' : ($o >= 5 ? 'text-pitch-ink' : 'text-pitch-muted'));
@endphp

<div class="space-y-6">
    {{-- Kişisel istatistik --}}
    <div>
        <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-1">İstatistiklerim</h3>
        <p class="text-sm text-pitch-muted mb-4">Tüm gruplardaki kayıtlı maçlardan hesaplanır.</p>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $cards = [
                    ['label' => 'Oynadığın Maç', 'value' => $stats['played'], 'icon' => '🏟️'],
                    ['label' => 'Kazanma Oranı', 'value' => '%'.$stats['winRate'], 'icon' => '📈'],
                    ['label' => 'Attığın Gol', 'value' => $stats['goals'], 'icon' => '⚽'],
                    ['label' => 'Aldığın MVP Oyu', 'value' => $stats['mvpVotes'], 'icon' => '🏆'],
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="bg-pitch-bg border border-pitch-line rounded-xl p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] uppercase tracking-widest text-pitch-muted">{{ $card['label'] }}</span>
                        <span>{{ $card['icon'] }}</span>
                    </div>
                    <div class="font-display text-2xl font-bold mt-1">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Saha profilleri (grup başına) --}}
    <div>
        <h3 class="font-display uppercase tracking-wider text-lg font-semibold mb-1">Saha Profilim</h3>
        <p class="text-sm text-pitch-muted mb-4">Her grupta oynadığın pozisyonları, tercih ettiğin ayağı ve forma numaranı buradan ayarlayabilirsin.</p>

        @forelse ($players as $player)
            <div class="border border-pitch-line rounded-xl p-4 mb-3">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3">
                        @php $ovr = $player->overall(); @endphp
                        @if ($player->overallIsPublic())
                            <span class="font-display text-2xl font-bold w-12 text-center {{ $tier($ovr) }}">{{ number_format($ovr, 1) }}</span>
                        @else
                            <span class="font-display text-2xl font-bold w-12 text-center text-pitch-muted" title="Puan, yeterli oylama olunca görünür">?</span>
                        @endif
                        <div>
                            <div class="font-semibold">{{ $player->group->name }}</div>
                            <div class="flex gap-1 mt-1 items-center flex-wrap">
                                @foreach ($player->positions ?? [] as $i => $pos)
                                    <span class="text-[10.5px] font-bold tracking-wide px-2 py-0.5 rounded-full bg-pitch-bg border border-pitch-line {{ $pos === 'KL' ? 'text-gold' : 'text-pitch-muted' }}">
                                        {{ count($player->positions) > 1 ? ($i + 1).'·' : '' }}{{ $pos }}
                                    </span>
                                @endforeach
                                <span class="text-[10.5px] font-bold tracking-wide px-2 py-0.5 rounded-full bg-pitch-bg border border-pitch-line text-bibB">🦶 {{ $player->footBadge() }}</span>
                                @if ($player->shirt_number)
                                    <span class="text-xs text-pitch-muted ms-1">#{{ $player->shirt_number }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($editingPlayerId !== $player->id)
                        <x-secondary-button wire:click="edit({{ $player->id }})">Düzenle</x-secondary-button>
                    @endif
                </div>

                @if ($editingPlayerId === $player->id)
                    <div class="mt-3 border-t border-pitch-line pt-3 space-y-3">
                        <div class="text-xs uppercase tracking-widest text-pitch-muted">Pozisyonlar — tıklama sırası önceliği belirler. Kaleci başka pozisyonlarla birleşebilir.</div>
                        <div class="flex gap-2 flex-wrap">
                            @foreach (Attributes::POSITIONS as $code => $label)
                                @php $rank = array_search($code, $posOrder, true); @endphp
                                <button type="button" wire:click="togglePosition('{{ $code }}')"
                                        class="relative px-3 py-1.5 rounded-full border text-sm font-semibold transition
                                               {{ $rank !== false ? 'bg-bibB/15 border-bibB text-bibB' : 'bg-pitch-bg border-pitch-line text-pitch-muted hover:brightness-125' }}">
                                    {{ $label }}
                                    @if ($rank !== false && count($posOrder) > 1)
                                        <span class="absolute -top-2 -right-1 w-4 h-4 rounded-full bg-gold text-pitch-bg text-[10px] font-extrabold leading-4">{{ $rank + 1 }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <select wire:model="editFoot" class="bg-pitch-bg border-pitch-line text-pitch-ink rounded-md text-sm focus:border-bibB focus:ring-bibB/40" aria-label="Tercih edilen ayak">
                                @foreach (Attributes::FEET as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-text-input wire:model="editNumber" type="number" min="1" max="99" class="w-28" placeholder="Forma No" />
                            <x-primary-button wire:click="save" type="button">Kaydet</x-primary-button>
                            <x-secondary-button wire:click="cancel">Vazgeç</x-secondary-button>
                        </div>
                        <x-input-error :messages="$errors->get('posOrder')" />
                        <x-input-error :messages="$errors->get('editNumber')" />
                    </div>
                @endif
            </div>
        @empty
            <p class="text-pitch-muted text-sm">Henüz bir gruba ait oyuncu kaydın yok. Bir gruba katılınca burada görünecek.</p>
        @endforelse
    </div>
</div>
