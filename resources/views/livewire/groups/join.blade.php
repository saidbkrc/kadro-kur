<div class="py-10">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-pitch-surface border border-pitch-line rounded-xl">
            <div class="p-8 text-center space-y-4">
                <div class="text-4xl">⚽</div>
                <h2 class="font-display uppercase tracking-wider text-2xl font-bold">{{ $group->name }}</h2>
                @if ($group->description)
                    <p class="text-pitch-muted">{{ $group->description }}</p>
                @endif
                <p class="text-sm text-pitch-muted">{{ $memberCount }} üye · Başkan: {{ $group->owner->name }}</p>

                @auth
                    <p>Bu gruba davet edildin. Katılmak istiyor musun?</p>
                    <div class="flex justify-center gap-3">
                        <x-primary-button wire:click="join">Gruba Katıl</x-primary-button>
                        <a href="{{ route('dashboard') }}" wire:navigate>
                            <x-secondary-button>Vazgeç</x-secondary-button>
                        </a>
                    </div>
                @else
                    <p>Bu gruba davet edildin! Katılmak için önce ücretsiz bir hesap oluştur — kayıt sonrası otomatik olarak buraya dönersin.</p>
                    <div class="flex justify-center gap-3">
                        <a href="{{ route('register') }}" wire:navigate>
                            <x-primary-button type="button">Kayıt Ol</x-primary-button>
                        </a>
                        <a href="{{ route('login') }}" wire:navigate>
                            <x-secondary-button>Zaten hesabım var</x-secondary-button>
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</div>
