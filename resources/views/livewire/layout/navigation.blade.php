<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav
    x-data="{
        open: false,
        pwaInstallable: false,
        isIOS: /iphone|ipad|ipod/i.test(navigator.userAgent),
        standalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        showIosHelp: false,
        init() {
            if (window.__pwaPrompt) this.pwaInstallable = true;
        },
        get canInstall() {
            return !this.standalone && (this.pwaInstallable || this.isIOS);
        },
        async install() {
            if (window.__pwaPrompt) {
                window.__pwaPrompt.prompt();
                await window.__pwaPrompt.userChoice;
                window.__pwaPrompt = null;
                this.pwaInstallable = false;
            } else if (this.isIOS) {
                this.open = false;
                this.showIosHelp = true;
            }
        },
    }"
    @pwa-installable.window="pwaInstallable = true"
    @pwa-installed.window="pwaInstallable = false; standalone = true"
    class="relative z-50 bg-pitch-surface/80 border-b border-pitch-line backdrop-blur"
>
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                        <span class="text-2xl">⚽</span>
                        <span class="font-display uppercase tracking-wider text-xl font-bold text-pitch-ink">Kadro Kur</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                @auth
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        Ana Sayfa
                    </x-nav-link>
                    <x-nav-link :href="route('groups.index')" :active="request()->routeIs('groups.*') || request()->routeIs('matches.*')" wire:navigate>
                        Gruplarım
                    </x-nav-link>
                </div>
                @endauth
            </div>

            <!-- Settings Dropdown -->
            @guest
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
                <a href="{{ route('login') }}" wire:navigate class="text-sm font-medium text-pitch-muted hover:text-pitch-ink">Giriş Yap</a>
                <a href="{{ route('register') }}" wire:navigate class="text-sm font-semibold text-bibB hover:underline ms-3">Kayıt Ol</a>
            </div>
            @endguest
            @auth
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-pitch-muted bg-transparent hover:text-pitch-ink focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <button x-show="canInstall" x-cloak @click="install()" class="w-full text-start">
                            <x-dropdown-link>
                                📲 Uygulamayı Yükle
                            </x-dropdown-link>
                        </button>

                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
            @endauth

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-pitch-muted hover:text-pitch-ink hover:bg-pitch-surface2 focus:outline-none focus:bg-pitch-surface2 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        @auth
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                Ana Sayfa
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('groups.index')" :active="request()->routeIs('groups.*') || request()->routeIs('matches.*')" wire:navigate>
                Gruplarım
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-pitch-line">
            <div class="px-4">
                <div class="font-medium text-base text-pitch-ink" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-pitch-muted">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <button x-show="canInstall" x-cloak @click="install()" class="w-full text-start">
                    <x-responsive-nav-link>
                        📲 Uygulamayı Yükle
                    </x-responsive-nav-link>
                </button>

                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
        @endauth
        @guest
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('login')" wire:navigate>Giriş Yap</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('register')" wire:navigate>Kayıt Ol</x-responsive-nav-link>
            <button x-show="canInstall" x-cloak @click="install()" class="w-full text-start">
                <x-responsive-nav-link>
                    📲 Uygulamayı Yükle
                </x-responsive-nav-link>
            </button>
        </div>
        @endguest
    </div>

    <!-- iOS "Ana Ekrana Ekle" talimatı (Safari beforeinstallprompt desteklemez) -->
    <div x-show="showIosHelp" x-cloak
         class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center bg-black/60 p-4"
         @click.self="showIosHelp = false">
        <div class="w-full max-w-sm rounded-2xl bg-pitch-surface border border-pitch-line p-5 text-pitch-ink shadow-xl">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-display uppercase tracking-wider text-lg font-bold">📲 Uygulamayı Yükle</h3>
                <button @click="showIosHelp = false" class="text-pitch-muted hover:text-pitch-ink text-xl leading-none">&times;</button>
            </div>
            <p class="text-sm text-pitch-muted mb-3">iPhone / iPad'de Kadro Kur'u ana ekrana eklemek için:</p>
            <ol class="space-y-2 text-sm">
                <li class="flex gap-2"><span class="text-bibB font-bold">1.</span> Safari'nin altındaki <span class="font-semibold">Paylaş</span> butonuna dokun <span class="text-pitch-muted">(kare + ok ⬆️)</span></li>
                <li class="flex gap-2"><span class="text-bibB font-bold">2.</span> <span class="font-semibold">Ana Ekrana Ekle</span> seçeneğini bul</li>
                <li class="flex gap-2"><span class="text-bibB font-bold">3.</span> Sağ üstten <span class="font-semibold">Ekle</span>'ye dokun</li>
            </ol>
        </div>
    </div>
</nav>
