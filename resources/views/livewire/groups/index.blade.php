<div class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-display uppercase tracking-wider text-2xl font-bold">Gruplarım</h2>
            <x-secondary-button wire:click="$toggle('showForm')">
                {{ $showForm ? 'Vazgeç' : '+ Yeni Grup' }}
            </x-secondary-button>
        </div>

        @if ($showForm)
            <div class="bg-pitch-surface border border-pitch-line rounded-xl">
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
                    <x-primary-button>Grubu Kur</x-primary-button>
                </form>
            </div>
        @endif

        @forelse ($groups as $group)
            <a href="{{ route('groups.show', $group) }}" wire:navigate
               class="block bg-pitch-surface border border-pitch-line rounded-xl hover:bg-pitch-surface2 transition">
                <div class="p-6 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-lg">{{ $group->name }}</div>
                        @if ($group->description)
                            <div class="text-sm text-pitch-muted mt-1">{{ $group->description }}</div>
                        @endif
                    </div>
                    <div class="text-sm text-pitch-muted shrink-0 ms-4">
                        {{ $group->members_count }} üye
                        @if ($group->owner_id === auth()->id())
                            <span class="ms-2 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gold/15 text-gold">Başkan</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="bg-pitch-surface border border-dashed border-pitch-line rounded-xl">
                <div class="p-8 text-center text-pitch-muted">
                    Henüz bir grubun yok.<br><strong class="text-pitch-ink">Yeni Grup</strong> ile ilk grubunu kur, davet linkini arkadaşlarına gönder. ⚽
                </div>
            </div>
        @endforelse
    </div>
</div>
