// Installable app shell + push notifications. Pages are always fetched
// live (they contain private lead data, so they are never cached); only
// static files are, plus an offline page shown when there is no connection.
const CACHE = 'crm-static-v2';
const STATIC = ['/offline.html', '/css/app.css', '/js/app.js', '/icons/icon-192.png', '/icons/favicon-32.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    const url = new URL(request.url);
    if (url.origin === location.origin && /^\/(css|js|icons)\//.test(url.pathname)) {
        // Serve from cache, refresh in the background.
        event.respondWith(caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(request);
            const network = fetch(request).then((response) => {
                if (response.ok) cache.put(request, response.clone());
                return response;
            }).catch(() => cached);
            return cached || network;
        }));
    }
});

// A push from the server: { title, body, url }.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: event.data?.text() }; }

    event.waitUntil(self.registration.showNotification(data.title || 'New activity', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/favicon-32.png',
        data: { url: data.url || '/notifications' },
    }));
});

// Tapping a notification opens (or focuses) the page it points to.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/notifications';

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const win of windows) {
            if (win.url === url && 'focus' in win) return win.focus();
        }
        return self.clients.openWindow(url);
    }));
});
