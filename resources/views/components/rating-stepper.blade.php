@props(['player', 'label', 'value' => null])

{{-- Başkan puanı −/+ (adım Player::GUEST_RATING_STEP). value null = henüz verilmedi ("—"). --}}
@php
    $min = \App\Models\Player::GUEST_RATING_MIN;
    $max = \App\Models\Player::GUEST_RATING_MAX;
@endphp
<span class="inline-flex items-center gap-1.5">
    <span>{{ $label }}:</span>
    <button type="button" wire:click="adjustGuestRating({{ $player->id }}, -1)"
            @disabled($value !== null && $value <= $min)
            class="w-6 h-6 rounded border border-pitch-line bg-pitch-bg text-pitch-ink leading-none hover:border-[#FF8A8A] disabled:opacity-30"
            title="Puanı düşür">−</button>
    <strong class="text-pitch-ink font-display text-sm w-7 text-center">{{ $value !== null ? number_format($value, 1) : '—' }}</strong>
    <button type="button" wire:click="adjustGuestRating({{ $player->id }}, 1)"
            @disabled($value !== null && $value >= $max)
            class="w-6 h-6 rounded border border-pitch-line bg-pitch-bg text-pitch-ink leading-none hover:border-bibB disabled:opacity-30"
            title="Puanı artır">+</button>
    {{ $slot }}
</span>
