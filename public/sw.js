// Kadro Kur — minimal service worker (PWA "ana ekrana ekle" için)
const OFFLINE_MESSAGE = 'Çevrimdışısın. Bağlantı gelince sayfayı yenile.';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Yalnızca sayfa gezinmelerini ele al (network-first); varlıklar ve Livewire istekleri dokunulmadan geçer.
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(
            () => new Response(OFFLINE_MESSAGE, {
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            })
        )
    );
});
