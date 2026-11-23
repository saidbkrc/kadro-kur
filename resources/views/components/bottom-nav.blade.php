@php
    // Mobil alt menü (uygulama hissi). Grup bağlamı: sayfanın kendi grubu → maçın grubu → kullanıcının ilk grubu.
    // Rota modeli mount'taki üyelik kapısından geçtiği için burada yalnızca link üretiliyor.
    $routeGroup = request()->route('group');
    $routeMatch = request()->route('match');
    $navGroup = $routeGroup instanceof \App\Models\Group ? $routeGroup
        : ($routeMatch instanceof \App\Models\FootballMatch ? $routeMatch->group : auth()->user()->groups()->orderBy('name')->first());

    $navMatch = $navGroup?->matches()->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->first()
        ?? $navGroup?->matches()->where('status', 'completed')->orderByDesc('starts_at')->first();
    $navPlayer = $navGroup?->playerFor(auth()->user());

    $items = [
        ['label' => 'Ana sayfa', 'href' => route('dashboard'), 'on' => request()->routeIs('dashboard'),
         'icon' => '<path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-6h6v6"/>'],
    ];
    if ($navGroup) {
        $items[] = ['label' => 'Maç', 'href' => $navMatch ? route('matches.show', $navMatch) : route('groups.show', $navGroup),
            'on' => request()->routeIs('matches.*') || request()->routeIs('groups.show'),
            'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16"/><circle cx="12" cy="12" r="3"/>'];
        $items[] = ['label' => 'İstatistik', 'href' => route('groups.stats', $navGroup), 'on' => request()->routeIs('groups.stats'),
            'icon' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>'];
        $items[] = ['label' => 'Kehanet', 'href' => route('groups.kehanet', $navGroup), 'on' => request()->routeIs('groups.kehanet'),
            'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'];
        $items[] = ['label' => 'Kartım', 'href' => $navPlayer ? route('groups.player', [$navGroup, $navPlayer]) : route('profile'),
            'on' => request()->routeIs('profile')
                || (request()->routeIs('groups.player') && $navPlayer && request()->route('player')?->id === $navPlayer->id),
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'];
    } else {
        $items[] = ['label' => 'Gruplar', 'href' => route('groups.index'), 'on' => request()->routeIs('groups.*'),
            'icon' => '<circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5"/><path d="M16 14.5c3 0 6 1.5 6 4.5"/>'];
        $items[] = ['label' => 'Profil', 'href' => route('profile'), 'on' => request()->routeIs('profile'),
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'];
    }
@endphp

<nav aria-label="Alt menü"
     class="sm:hidden fixed inset-x-0 bottom-0 z-40 bg-pitch-bg/95 backdrop-blur border-t border-pitch-line pb-[env(safe-area-inset-bottom)]">
    <div class="flex items-stretch justify-around px-1">
        @foreach ($items as $item)
            <a href="{{ $item['href'] }}" wire:navigate @if ($item['on']) aria-current="page" @endif
               class="flex-1 min-h-[60px] flex flex-col items-center justify-center gap-1 text-[11px] font-semibold transition
                      {{ $item['on'] ? 'text-bibB' : 'text-pitch-muted hover:text-pitch-ink' }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
