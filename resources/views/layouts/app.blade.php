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
        <meta name="vapid-key" content="{{ config('webpush.vapid.public_key') }}">
        <link rel="icon" href="/icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icon-192.png">

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
            // PWA: yükleme isteğini yakala (menüdeki "Uygulamayı Yükle" butonu kullanır)
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.__pwaPrompt = e;
                window.dispatchEvent(new CustomEvent('pwa-installable'));
            });
            window.addEventListener('appinstalled', () => {
                window.__pwaPrompt = null;
                window.dispatchEvent(new CustomEvent('pwa-installed'));
            });

            // Web push aboneliği: izin varken sessizce senkronla; menü butonu enablePush() çağırır.
            (() => {
                const vapidKey = document.querySelector('meta[name="vapid-key"]')?.content;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return;

                const urlB64ToUint8 = (b64) => {
                    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
                    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
                    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
                };

                const subscribe = async () => {
                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlB64ToUint8(vapidKey),
                    });
                    await fetch('/push/abone', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify(sub.toJSON()),
                    });
                    window.dispatchEvent(new CustomEvent('push-enabled'));
                };

                // Menüdeki "Bildirimleri Aç" butonu bunu çağırır
                window.enablePush = async () => {
                    if (Notification.permission === 'denied') {
                        alert('Bildirim izni tarayıcıda engellenmiş. Site ayarlarından izin vermelisin.');
                        return;
                    }
                    const perm = await Notification.requestPermission();
                    if (perm === 'granted') await subscribe().catch(() => {});
                };

                // İzin zaten verilmişse aboneliği sunucuyla sessizce senkronla (cihaz/anahtar değişebilir)
                if (Notification.permission === 'granted') {
                    window.addEventListener('load', () => subscribe().catch(() => {}));
                }
            })();
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
    </body>
</html>
