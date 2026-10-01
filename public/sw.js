// Installable app shell. Pages are always fetched live (they contain private
// lead data, so they are never cached); only static files are, plus an
// offline page shown when there is no connection.
const CACHE = 'crm-static-v1';
const STATIC = ['/offline.html', '/css/app.css', '/icons/icon-192.png', '/icons/favicon-32.png'];

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
    if (url.origin === location.origin && (url.pathname.startsWith('/css/') || url.pathname.startsWith('/icons/'))) {
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
