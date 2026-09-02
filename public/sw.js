// Service worker mínimo para instalabilidad de la PWA. No cachea nada
// dinámico a propósito: Livewire es server-driven y servir HTML viejo desde
// caché sería activamente incorrecto en un POS/reporting. Solo assets
// estáticos de build (hash en el nombre de archivo) e íconos van cache-first.
const CACHE_NAME = 'localpos-static-v2';
const STATIC_PATTERNS = [/^\/build\//, /^\/icons\//];

function isStaticAsset(url) {
    return STATIC_PATTERNS.some((pattern) => pattern.test(url.pathname));
}

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin || !isStaticAsset(url)) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(event.request);
            if (cached) {
                return cached;
            }

            const response = await fetch(event.request);
            if (response.ok) {
                cache.put(event.request, response.clone());
            }
            return response;
        })
    );
});
