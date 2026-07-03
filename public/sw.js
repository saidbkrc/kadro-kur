// Kadro Kur — minimal service worker (PWA "ana ekrana ekle" için)
const OFFLINE_MESSAGE = 'Çevrimdışısın. Bağlantı gelince sayfayı yenile.';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Web push: sunucudan gelen bildirimi göster.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { /* bozuk payload */ }

    event.waitUntil(self.registration.showNotification(data.title || 'Kadro Kur', {
        body: data.body || '',
        icon: data.icon || '/icon-192.png',
        badge: data.badge || '/icon-192.png',
        tag: data.tag || undefined,
        data: { url: (data.data && data.data.url) || '/' },
    }));
});

// Bildirime tıklayınca ilgili sayfayı aç (açık pencere varsa ona git).
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});

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
