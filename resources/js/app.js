import './bootstrap';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const role = window.__SYNC_ROLE__ || 'source';

        navigator.serviceWorker.register(`/sw.js?role=${encodeURIComponent(role)}`).catch(() => {
            // Instalación de PWA no disponible en este navegador; no es crítico.
        });
    });
}
