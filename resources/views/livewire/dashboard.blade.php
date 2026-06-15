<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <h2 class="font-display uppercase tracking-wider text-2xl font-bold">Merhaba, {{ auth()->user()->name }} 👋</h2>

        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6 space-y-4">
            <h3 class="font-display uppercase tracking-wider text-lg font-semibold">Yaklaşan Maçlar</h3>

            @forelse ($upcoming as $match)
                @php $rsvp = $myRsvps[$match->id] ?? null; @endphp
                <a href="{{ route('matches.show', $match) }}" wire:navigate
                   class="block border border-pitch-line rounded-lg p-4 hover:bg-pitch-surface2 transition">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="font-semibold">{{ $match->title }}</div>
                            <div class="text-sm text-pitch-muted mt-0.5">
                                {{ $match->group->name }} · {{ $match->starts_at->translatedFormat('d F Y, l H:i') }}
                                @if ($match->location) · {{ $match->location }} @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-end space-y-1">
                            <div class="font-display text-lg font-bold {{ $match->going_count >= $match->capacity ? 'text-bibA' : '' }}">
                                {{ $match->going_count }}/{{ $match->capacity }}
                            </div>
                            @if ($rsvp?->status === 'going' && $rsvp->waitlist_position !== null)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Yedek {{ $rsvp->waitlist_position }}.</span>
                            @elseif ($rsvp?->status === 'going')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-bibB/15 text-bibB">Geliyorsun ✓</span>
                            @elseif ($rsvp?->status === 'maybe')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Belki</span>
                            @elseif ($rsvp?->status === 'not_going')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-500/15 text-[#FF8A8A]">Gelmiyorsun</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-pitch-surface2 text-pitch-muted">Cevap bekleniyor</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <p class="text-pitch-muted">
                    Yaklaşan maç yok.
                    @if ($groupCount === 0)
                        Önce <a href="{{ route('groups.index') }}" wire:navigate class="text-bibB hover:underline font-medium">bir grup kur</a> ya da davet linkiyle bir gruba katıl.
                    @endif
                </p>
            @endforelse
        </div>

        <div class="bg-pitch-surface border border-pitch-line rounded-xl p-6">
            <a href="{{ route('groups.index') }}" wire:navigate class="text-bibB hover:underline font-medium">
                Gruplarım ({{ $groupCount }}) →
            </a>
        </div>
    </div>
</div>
