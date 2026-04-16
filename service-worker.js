const CACHE_NAME = 'podmostki-shell-v5';
const APP_SHELL = [
    '/',
    '/index.php',
    '/shop.php',
    '/player.php',
    '/success.php',
    '/fail.php',
    '/manifest.json',
    '/assets/styles.css',
    '/assets/player.js',
    '/assets/entry.js',
    '/assets/shop.js',
    '/assets/payment-result.js',
    '/assets/icons/play.svg',
    '/assets/icons/pause.svg',
    '/assets/icons/prev.svg',
    '/assets/icons/next.svg',
    '/assets/logo.png'
];

async function cacheAppShellSafely() {
    const cache = await caches.open(CACHE_NAME);

    await Promise.all(APP_SHELL.map(async (url) => {
        try {
            const response = await fetch(url, { cache: 'no-cache' });
            if (!response || !response.ok) {
                return;
            }
            await cache.put(url, response);
        } catch (e) {
            // Пропускаем отсутствующие или недоступные файлы, чтобы SW всё равно установился.
        }
    }));
}

self.addEventListener('install', event => {
    event.waitUntil(cacheAppShellSafely());
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET') {
        return;
    }

    if (url.pathname.startsWith('/storage/audio/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
        return;
    }

    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) return cached;

            return fetch(event.request).then(networkResponse => {
                if (networkResponse && networkResponse.ok) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
                }
                return networkResponse;
            });
        })
    );
});