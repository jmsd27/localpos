// Service worker mínimo para instalabilidad de la PWA.
//
// En la instancia "source" (POS local por LAN) no cachea nada dinámico a
// propósito: Livewire es server-driven y servir HTML viejo desde caché sería
// activamente incorrecto en un POS (precios, stock y estado de mesa deben
// ser siempre en vivo).
//
// En la instancia "mirror" (espejo de solo lectura en el dominio/nube) sí
// cachea la última página vista: ya es una reflexión con cierto atraso, así
// que mostrar "lo último visto" cuando se corta la conexión es mejor que una
// pantalla de error, y no hay riesgo de escribir datos viejos porque el
// mirror nunca escribe (bloqueado por Gate::before, ver AppServiceProvider).
// El rol llega como query string al registrar el worker (resources/js/app.js).
const CACHE_NAME = 'localpos-static-v2';
const PAGES_CACHE = 'puntoya-mirror-pages-v1';
const STATIC_PATTERNS = [/^\/build\//, /^\/icons\//];
const isMirror = new URL(self.location.href).searchParams.get('role') === 'mirror';

function isStaticAsset(url) {
    return STATIC_PATTERNS.some((pattern) => pattern.test(url.pathname));
}

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME && key !== PAGES_CACHE).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (isStaticAsset(url)) {
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
        return;
    }

    // Solo el espejo cachea páginas HTML, y siempre pide red primero: nunca
    // sirve algo viejo si hay conexión, solo cuando el fetch falla.
    if (isMirror && event.request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                const cache = await caches.open(PAGES_CACHE);

                try {
                    const response = await fetch(event.request);
                    if (response.ok) {
                        cache.put(event.request, response.clone());
                    }
                    return response;
                } catch (error) {
                    const cached = await cache.match(event.request);
                    return cached || Response.error();
                }
            })()
        );
    }
});
