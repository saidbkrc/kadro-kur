<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kadro Kur') }}</title>

        {{-- PWA: ana ekrana ekle --}}
        <meta name="theme-color" content="#15502F">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Kadro Kur">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icon.svg">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|barlow-condensed:500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak]{display:none !important}</style>
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
            }
        </script>
    </head>
    <body class="font-sans antialiased text-pitch-ink overflow-x-hidden">
        <div class="min-h-screen overflow-x-hidden bg-pitch-bg bg-[radial-gradient(1200px_500px_at_50%_-10%,rgba(40,120,70,.25),transparent_60%)]">
            <livewire:layout.navigation />

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        {{-- Özel onay modalı: tarayıcının confirm() kutusunun yerine. $dispatch('kk-confirm', {message, cb}) ile açılır. --}}
        <div x-data="{ open: false, message: '', danger: true, _cb: null }"
             x-on:kk-confirm.window="message = $event.detail.message; danger = $event.detail.danger ?? true; _cb = $event.detail.cb; open = true"
             x-on:keydown.escape.window="open = false"
             x-show="open" x-cloak
             class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
            <div class="relative bg-pitch-surface border border-pitch-line rounded-xl shadow-2xl max-w-sm w-full p-6"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-start gap-3">
                    <span class="text-2xl" x-text="danger ? '⚠️' : '❓'"></span>
                    <p class="text-pitch-ink leading-relaxed" x-text="message"></p>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="open = false"
                            class="px-4 py-2 rounded-md text-sm font-semibold bg-pitch-surface2 border border-pitch-line hover:brightness-125">
                        Vazgeç
                    </button>
                    <button type="button" x-ref="kkOk"
                            @click="const cb = _cb; open = false; _cb = null; if (cb) cb()"
                            :class="danger ? 'bg-red-700 border-red-600' : 'bg-gradient-to-b from-[#2C7A48] to-[#1F5A35] border-[#3E9A60]'"
                            class="px-4 py-2 rounded-md text-sm font-semibold border text-pitch-ink hover:brightness-125">
                        Evet
                    </button>
                </div>
            </div>
        </div>
    </body>
</html>
