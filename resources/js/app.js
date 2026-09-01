import './bootstrap';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Instalación de PWA no disponible en este navegador; no es crítico.
        });
    });
}
