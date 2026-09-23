@props(['group'])

{{-- Grup duyuruları (App\Support\Announcements). Kapatma yalnızca bu tarayıcıda hatırlanır. --}}
@foreach (\App\Support\Announcements::active() as $duyuru)
    <div x-data="{
            anahtar: 'duyuru-kapandi-{{ $duyuru['id'] }}',
            acik: true,
            init() { try { this.acik = localStorage.getItem(this.anahtar) !== '1'; } catch (e) {} },
            kapat() { this.acik = false; try { localStorage.setItem(this.anahtar, '1'); } catch (e) {} },
         }"
         x-show="acik" x-cloak
         class="bg-gold/5 border border-gold/40 rounded-xl p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="font-display uppercase tracking-wider text-base font-semibold text-gold">{{ $duyuru['title'] }}</h3>
            <button type="button" x-on:click="kapat()" class="text-pitch-muted hover:text-pitch-ink text-lg leading-none shrink-0" title="Kapat">&times;</button>
        </div>

        <ul class="mt-2 space-y-1 text-sm text-pitch-ink">
            @foreach ($duyuru['lines'] as $satir)
                <li>{{ $satir }}</li>
            @endforeach
        </ul>

        @if ($duyuru['link'] === 'kehanet' && ! request()->routeIs('groups.kehanet'))
            <a href="{{ route('groups.kehanet', $group) }}" wire:navigate class="inline-block mt-3 text-xs text-bibB hover:underline">🔮 Kehanet'e git →</a>
        @endif
    </div>
@endforeach
