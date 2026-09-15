// TOY & JOY Minimal Static Service Worker Shell
// Strictly no caching of authenticated/private responses or dynamic routes.

const CACHE_NAME = 'toy-joy-shell-v1';
const STATIC_ASSETS = [
    '/favicon.ico',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            )
        )
    );
    self.clients.claim();
});

// Network-only for all dynamic and navigation requests to ensure no sensitive response caching
self.addEventListener('fetch', (event) => {
    // Pass through to network for all requests (no offline auth/private response caching)
    return;
});
