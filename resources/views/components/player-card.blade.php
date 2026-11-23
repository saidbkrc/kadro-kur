@props(['player', 'group' => null, 'size' => 'md'])
@php
    // Kalkan biçimli oyuncu kartı (oyun menüsü tarzı). Renk kademesi nihai puana göre:
    // 8+ altın · 6.5+ yeşil · altı gümüş · puan gizliyse koyu.
    $public = $player->overallIsPublic();
    $rating = $public ? $player->displayRating() : null;
    $delta = $public ? $player->formDelta() : null;

    $tier = match (true) {
        $rating === null => 'bg-[linear-gradient(165deg,#2A3350_0%,#1A2139_100%)] text-pitch-ink',
        $rating >= 8 => 'bg-[linear-gradient(165deg,#FFF0B8_0%,#F2BF45_45%,#A86E12_100%)] text-[#3A2600]',
        $rating >= 6.5 => 'bg-[linear-gradient(165deg,#F0FFC2_0%,#C8F04B_50%,#5E8A12_100%)] text-[#1E2B00]',
        default => 'bg-[linear-gradient(165deg,#F5F8FD_0%,#B7C2D6_50%,#5D6A84_100%)] text-[#1B2233]',
    };
    $pos = $player->positions[0] ?? null;
    $big = $size === 'lg';
    $tag = $group ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($group) href="{{ route('groups.player', [$group, $player]) }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'relative flex flex-col items-center text-center [clip-path:polygon(0_0,100%_0,100%_84%,50%_100%,0_84%)] transition hover:brightness-110 '.$tier.' '.($big ? 'h-44 px-3 pt-3' : 'h-36 px-2.5 pt-2.5')]) }}>
    <div class="self-stretch flex items-start justify-between">
        <div class="text-start leading-none">
            <div class="font-display font-extrabold {{ $big ? 'text-4xl' : 'text-3xl' }}">{{ $rating !== null ? number_format($rating, 1) : '?' }}</div>
            @if ($pos)<div class="font-display font-bold text-xs mt-0.5">{{ $pos }}</div>@endif
        </div>
        @if ($delta !== null && abs($delta) >= 0.05)
            <span class="text-[11px] font-bold" title="Son 5 maç formu">{{ $delta > 0 ? '▲' : '▼' }}{{ number_format(abs($delta), 1) }}</span>
        @endif
    </div>
    <div class="{{ $big ? 'w-12 h-12 text-lg' : 'w-10 h-10 text-sm' }} mt-0.5 rounded-full bg-black/15 flex items-center justify-center font-bold">
        {{ mb_strtoupper(mb_substr($player->name, 0, 1)) }}
    </div>
    <div class="font-display font-extrabold uppercase tracking-wide {{ $big ? 'text-lg' : 'text-[15px]' }} mt-1 leading-tight max-w-full truncate">{{ $player->name }}</div>
</{{ $tag }}>
