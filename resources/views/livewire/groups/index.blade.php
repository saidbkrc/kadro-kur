<div class="py-6 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-display uppercase tracking-wide text-3xl sm:text-4xl font-extrabold leading-none">Gruplarım</h2>
            <x-secondary-button wire:click="$toggle('showForm')">
                {{ $showForm ? 'Vazgeç' : '+ Yeni Grup' }}
            </x-secondary-button>
        </div>

        @if ($showForm)
            <div class="bg-pitch-surface border border-pitch-line rounded-3xl">
                <form wire:submit="create" class="p-6 space-y-4">
                    <div>
                        <x-input-label for="name" value="Grup adı" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Salı Akşamı Halı Saha" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="description" value="Açıklama (isteğe bağlı)" />
                        <textarea wire:model="description" id="description" rows="2"
                                  class="mt-1 block w-full bg-pitch-bg border-pitch-line text-pitch-ink placeholder-pitch-muted/60 focus:border-bibB focus:ring-bibB/40 rounded-md shadow-sm"
                                  placeholder="Her salı 21:00, Yıldız Halı Saha"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label value="Format" />
                        <div class="mt-1 flex gap-2 flex-wrap">
                            @foreach (\App\Support\Attributes::TEAM_FORMATS as $boyut => $etiket)
                                <button type="button" wire:click="$set('format', {{ $boyut }})"
                                        class="px-4 py-2 rounded-md border text-sm font-semibold transition
                                               {{ $format === $boyut ? 'border-bibB bg-bibB/10 text-bibB' : 'border-pitch-line text-pitch-muted hover:bg-pitch-surface2' }}">
                                    {{ $etiket }}
                                </button>
                            @endforeach
                        </div>
                        <p class="text-xs text-pitch-muted mt-1">Kadro kapasitesi ve saha dizilişi buna göre ayarlanır; sonradan grup ayarlarından değiştirebilirsin.</p>
                        <x-input-error :messages="$errors->get('format')" class="mt-2" />
                    </div>
                    <x-primary-button class="w-full sm:w-auto">Grubu Kur</x-primary-button>
                </form>
            </div>
        @endif

        @forelse ($groups as $group)
            <a href="{{ route('groups.show', $group) }}" wire:navigate
               class="flex items-center gap-4 bg-pitch-surface border border-pitch-line rounded-3xl p-4 sm:p-5 hover:border-bibB/50 transition">
                <span class="w-14 h-14 shrink-0 rounded-2xl bg-bibB/10 text-bibB flex items-center justify-center font-display text-3xl font-extrabold">
                    {{ mb_strtoupper(mb_substr($group->name, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-display uppercase tracking-wide text-xl font-extrabold truncate">{{ $group->name }}</span>
                    @if ($group->description)
                        <span class="block text-sm text-pitch-muted truncate">{{ $group->description }}</span>
                    @endif
                </span>
                <span class="text-sm text-pitch-muted shrink-0 text-end">
                    {{ $group->members_count }} üye
                    @if ($group->owner_id === auth()->id())
                        <span class="block mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gold/15 text-gold">Başkan</span>
                    @endif
                </span>
            </a>
        @empty
            <div class="bg-pitch-surface border border-dashed border-pitch-line rounded-3xl">
                <div class="p-8 text-center text-pitch-muted">
                    Henüz bir grubun yok.<br><strong class="text-pitch-ink">Yeni Grup</strong> ile ilk grubunu kur, davet linkini arkadaşlarına gönder. ⚽
                </div>
            </div>
        @endforelse
    </div>
</div>
