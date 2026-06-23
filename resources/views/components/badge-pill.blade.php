@props(['badge'])

@php
    $earned = $badge['earned'];
    $pct = round($badge['progress'] * 100);
@endphp

<div
    {{ $attributes->merge(['class' => 'relative rounded-xl border p-3 text-center transition '
        . ($earned
            ? 'bg-pitch-surface2 border-gold/60 shadow-[0_0_0_1px_rgba(212,175,55,.15)]'
            : 'bg-pitch-surface border-pitch-line')]) }}
    title="{{ $badge['name'] }} — {{ $badge['desc'] }}"
>
    <div class="text-3xl leading-none mb-1 {{ $earned ? '' : 'opacity-30 grayscale' }}">{{ $badge['icon'] }}</div>
    <div class="font-display uppercase tracking-wide text-xs font-bold {{ $earned ? 'text-pitch-ink' : 'text-pitch-muted' }}">
        {{ $badge['name'] }}
    </div>

    @if ($earned)
        <div class="text-[10px] text-gold tracking-widest mt-0.5">KAZANILDI</div>
    @else
        <div class="mt-1.5">
            <div class="h-1 rounded-full bg-pitch-line overflow-hidden">
                <div class="h-full bg-bibB/70 rounded-full" style="width: {{ $pct }}%"></div>
            </div>
            <div class="text-[10px] text-pitch-muted mt-0.5">{{ $badge['value'] }}/{{ $badge['goal'] }}</div>
        </div>
    @endif
</div>
