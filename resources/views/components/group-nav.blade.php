@props(['group', 'active' => null])

@php
    // Grup bağlamındaki her sayfada görünen hızlı erişim çubuğu
    $upcoming = $group->matches()
        ->where('status', 'scheduled')
        ->where('starts_at', '>=', now())
        ->orderBy('starts_at')
        ->first();

    // En son oynanmış maç (sonucu/oylaması burada)
    $lastMatch = $group->matches()
        ->where('status', 'completed')
        ->orderByDesc('starts_at')
        ->first();

    $myPlayer = $group->playerFor(auth()->user());

    $base = 'flex flex-col items-center justify-center gap-0.5 px-2 py-2.5 rounded-lg border text-center transition min-w-0';
    $on = 'bg-bibB/10 border-bibB text-bibB font-semibold';
    $off = 'bg-pitch-bg border-pitch-line text-pitch-ink hover:bg-pitch-surface2';
    $dead = 'bg-pitch-bg border-pitch-line text-pitch-muted opacity-50 cursor-default';
@endphp

<div class="bg-pitch-surface border border-pitch-line rounded-xl p-2">
    <div class="grid grid-cols-5 gap-1.5">

        {{-- Yaklaşan maç --}}
        @if ($upcoming)
            <a href="{{ route('matches.show', $upcoming) }}" wire:navigate
               class="{{ $base }} {{ $active === 'match' ? $on : $off }}">
                <span class="text-lg leading-none">⚽</span>
                <span class="text-[10px] leading-tight truncate w-full">{{ $upcoming->starts_at->translatedFormat('d M') }}</span>
            </a>
        @else
            <span class="{{ $base }} {{ $dead }}">
                <span class="text-lg leading-none">⚽</span>
                <span class="text-[10px] leading-tight">Maç yok</span>
            </span>
        @endif

        {{-- Son oynanan maç --}}
        @if ($lastMatch)
            <a href="{{ route('matches.show', $lastMatch) }}" wire:navigate
               class="{{ $base }} {{ $active === 'last' ? $on : $off }}">
                <span class="text-lg leading-none">🏁</span>
                <span class="text-[10px] leading-tight truncate w-full">Son Maç</span>
            </a>
        @else
            <span class="{{ $base }} {{ $dead }}">
                <span class="text-lg leading-none">🏁</span>
                <span class="text-[10px] leading-tight">Son Maç</span>
            </span>
        @endif

        {{-- İstatistikler --}}
        <a href="{{ route('groups.stats', $group) }}" wire:navigate
           class="{{ $base }} {{ $active === 'stats' ? $on : $off }}">
            <span class="text-lg leading-none">📊</span>
            <span class="text-[10px] leading-tight truncate w-full">İstatistik</span>
        </a>

        {{-- Kehanet (tahmin oyunu) --}}
        <a href="{{ route('groups.kehanet', $group) }}" wire:navigate
           class="{{ $base }} {{ $active === 'kehanet' ? $on : $off }}">
            <span class="text-lg leading-none">🔮</span>
            <span class="text-[10px] leading-tight truncate w-full">Kehanet</span>
        </a>

        {{-- Kendi oyuncu profilim --}}
        @if ($myPlayer)
            <a href="{{ route('groups.player', [$group, $myPlayer]) }}" wire:navigate
               class="{{ $base }} {{ $active === 'profile' ? $on : $off }}">
                <span class="text-lg leading-none">👤</span>
                <span class="text-[10px] leading-tight truncate w-full">Profilim</span>
            </a>
        @else
            <span class="{{ $base }} {{ $dead }}">
                <span class="text-lg leading-none">👤</span>
                <span class="text-[10px] leading-tight truncate w-full">Profilim</span>
            </span>
        @endif

    </div>
</div>
