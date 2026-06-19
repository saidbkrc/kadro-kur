@props(['player', 'numClass' => 'text-2xl w-12'])
@php
    $public = $player->overallIsPublic();
    $rating = $public ? $player->displayRating() : null;
    $delta = $public ? $player->formDelta() : null;
    $tier = $rating === null
        ? 'text-pitch-muted'
        : ($rating >= 8 ? 'text-gold' : ($rating >= 6.5 ? 'text-[#7DE39A]' : ($rating >= 5 ? 'text-pitch-ink' : 'text-pitch-muted')));
@endphp
<span class="inline-flex flex-col items-center leading-none shrink-0">
    @if ($public)
        <span class="font-display font-bold text-center {{ $numClass }} {{ $tier }}">{{ number_format($rating, 1) }}</span>
        @if ($delta !== null && abs($delta) >= 0.05)
            <span class="text-[9px] font-bold {{ $delta > 0 ? 'text-[#7DE39A]' : 'text-[#FF8A8A]' }}" title="Son 5 maç formu (performans katkısı)">{{ $delta > 0 ? '▲' : '▼' }}{{ number_format(abs($delta), 1) }}</span>
        @endif
    @else
        <span class="font-display font-bold text-center {{ $numClass }} text-pitch-muted" title="Puan, yeterli oylama olunca görünür">?</span>
    @endif
</span>
