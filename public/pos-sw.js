// POS Progressive Web App Service Worker (Step 8, POS-04, POS-08)
const CACHE_NAME = 'kcerp-pos-shell-v1';
const PRECACHE_ASSETS = [
    '/pos',
    '/vendor/pos/tailwind.js',
    '/vendor/pos/dexie.min.js',
    '/vendor/pos/alpine.min.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    // Navigation / POS HTML shell: Network-first, fallback to cache
    if (event.request.mode === 'navigate' || url.pathname === '/pos') {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return networkResponse;
                })
                .catch(() => {
                    return caches.match('/pos').then((cached) => {
                        return cached || new Response('Offline POS Shell Ready', {
                            headers: { 'Content-Type': 'text/html' }
                        });
                    });
                })
        );
        return;
    }

    // Static local vendor assets: Cache-first, fallback to network
    if (url.pathname.startsWith('/vendor/pos/')) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(event.request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Default: network with cache fallback
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
