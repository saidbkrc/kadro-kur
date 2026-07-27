@props(['history'])

@php
    // Maç maç performans puanı (1-10). Tek nokta varsa çizgi yerine sadece nokta gösterilir.
    $points = collect($history);
    $w = 320;
    $h = 110;
    $padX = 8;
    $padY = 12;
    $min = 1;
    $max = 10;

    $x = fn ($i) => $points->count() < 2
        ? $w / 2
        : $padX + ($i / ($points->count() - 1)) * ($w - 2 * $padX);
    $y = fn ($score) => $padY + (1 - ($score - $min) / ($max - $min)) * ($h - 2 * $padY);

    $coords = $points->map(fn ($p, $i) => ['x' => round($x($i), 1), 'y' => round($y($p['score']), 1)] + $p);
    $line = $coords->map(fn ($c) => $c['x'].','.$c['y'])->implode(' ');
    $area = $points->count() > 1 ? $line.' '.$coords->last()['x'].','.($h - $padY + 2).' '.$coords->first()['x'].','.($h - $padY + 2) : null;

    $first = $points->first()['score'];
    $last = $points->last()['score'];
    $delta = round($last - $first, 1);
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between mb-1">
        <span class="text-[11px] tracking-[.14em] text-pitch-muted">FORM GRAFİĞİ · SON {{ $points->count() }} MAÇ</span>
        @if ($points->count() > 1 && abs($delta) >= 0.05)
            <span class="text-xs font-bold {{ $delta > 0 ? 'text-[#7DE39A]' : 'text-[#FF8A8A]' }}">
                {{ $delta > 0 ? '▲' : '▼' }}{{ number_format(abs($delta), 1) }}
            </span>
        @endif
    </div>

    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto" preserveAspectRatio="none" role="img"
         aria-label="Performans puanı grafiği">
        {{-- Referans çizgileri: 5 (orta) ve 8 (iyi) --}}
        @foreach ([5, 8] as $ref)
            <line x1="0" y1="{{ round($y($ref), 1) }}" x2="{{ $w }}" y2="{{ round($y($ref), 1) }}"
                  stroke="currentColor" class="text-pitch-line" stroke-width="1" stroke-dasharray="3 4" />
        @endforeach

        @if ($area)
            <polyline points="{{ $area }}" fill="currentColor" class="text-bibB" opacity=".10" />
            <polyline points="{{ $line }}" fill="none" stroke="currentColor" class="text-bibB"
                      stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        @endif

        @foreach ($coords as $c)
            <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="3.5" fill="currentColor"
                    class="{{ $c['score'] >= 8 ? 'text-gold' : 'text-bibB' }}" />
            <title>{{ $c['date']->translatedFormat('d F') }} — {{ number_format($c['score'], 1) }}</title>
        @endforeach
    </svg>

    <div class="flex items-center justify-between text-[10px] text-pitch-muted mt-0.5">
        <span>{{ $points->first()['date']->translatedFormat('d M') }}</span>
        <span class="font-display font-bold text-sm {{ $last >= 8 ? 'text-gold' : 'text-pitch-ink' }}">{{ number_format($last, 1) }}</span>
        <span>{{ $points->last()['date']->translatedFormat('d M') }}</span>
    </div>
</div>
