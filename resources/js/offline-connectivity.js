// Detecta si el navegador puede hablar con el servidor, no solo si el
// dispositivo tiene wifi/datos: navigator.onLine no alcanza (puede haber
// wifi sin que el servidor responda). Expone Alpine.store('offline').online,
// que consumen el panel offline de mesas/comanda, el indicador del mapa de
// mesas y los botones de caja.
const HEARTBEAT_INTERVAL_MS = 15000;

function setOnline(online) {
    if (window.Alpine?.store('offline')) {
        window.Alpine.store('offline').online = online;
    }
}

async function heartbeat() {
    if (!navigator.onLine) {
        setOnline(false);
        return;
    }

    try {
        const response = await fetch('/up', { method: 'GET', cache: 'no-store' });
        setOnline(response.ok);
    } catch (error) {
        setOnline(false);
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('offline', { online: navigator.onLine });
});

window.addEventListener('online', heartbeat);
window.addEventListener('offline', () => setOnline(false));
window.addEventListener('load', () => {
    heartbeat();
    setInterval(heartbeat, HEARTBEAT_INTERVAL_MS);
});

export { heartbeat };
