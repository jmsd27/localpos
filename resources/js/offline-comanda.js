// Catálogo cacheado + borradores de comanda por mesa mientras no hay
// conexión, y la cola que los sincroniza al volver. Sin librerías nuevas:
// localStorage alcanza para el volumen de datos de un bar. Ver
// docs/superpowers/specs/2026-09-05-modo-offline-comandas-design.md.
const CATALOG_KEY = 'puntoya_offline_catalog';
const DRAFTS_KEY = 'puntoya_offline_drafts';
const CATALOG_REFRESH_MS = 5 * 60 * 1000;

function uuid() {
    return crypto.randomUUID();
}

function readJSON(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        return fallback;
    }
}

function writeJSON(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        // localStorage lleno o no disponible (modo incógnito): se sigue
        // funcionando en línea, solo se pierde el caché offline.
    }
}

function loadCatalog() {
    return readJSON(CATALOG_KEY, null);
}

async function refreshCatalog() {
    try {
        const response = await fetch('/mesas/catalogo-offline', { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            return loadCatalog();
        }

        const catalog = await response.json();
        writeJSON(CATALOG_KEY, catalog);

        return catalog;
    } catch (error) {
        return loadCatalog();
    }
}

function loadDrafts() {
    return readJSON(DRAFTS_KEY, {});
}

function saveDrafts(drafts) {
    writeJSON(DRAFTS_KEY, drafts);
}

function getDraft(tableId) {
    return loadDrafts()[tableId] ?? null;
}

function upsertDraft(tableId, mutator) {
    const drafts = loadDrafts();
    const current = drafts[tableId] ?? {
        table_id: tableId,
        client_order_uuid: null,
        existing_order_id: null,
        people_count: null,
        requested_bill: false,
        items: [],
        created_at: new Date().toISOString(),
    };

    drafts[tableId] = mutator(current);
    saveDrafts(drafts);

    return drafts[tableId];
}

function clearDraft(tableId) {
    const drafts = loadDrafts();
    delete drafts[tableId];
    saveDrafts(drafts);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function syncDraft(tableId, draft) {
    const response = await fetch(`/mesas/${tableId}/comanda/sincronizar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            client_order_uuid: draft.client_order_uuid,
            existing_order_id: draft.existing_order_id,
            people_count: draft.people_count,
            requested_bill: draft.requested_bill,
            items: draft.items,
        }),
    });

    if (!response.ok) {
        throw new Error(`sync_failed_${response.status}`);
    }

    return response.json();
}

async function syncAllDrafts() {
    const drafts = loadDrafts();
    const results = [];

    for (const [tableId, draft] of Object.entries(drafts)) {
        if (draft.items.length === 0 && !draft.requested_bill) {
            clearDraft(tableId);
            continue;
        }

        try {
            const result = await syncDraft(tableId, draft);
            clearDraft(tableId);
            results.push({ tableId, ok: true, order: result.order });
        } catch (error) {
            results.push({ tableId, ok: false, error: error.message });
        }
    }

    window.dispatchEvent(new CustomEvent('puntoya:drafts-synced', { detail: results }));

    return results;
}

document.addEventListener('alpine:init', () => {
    refreshCatalog();
    setInterval(refreshCatalog, CATALOG_REFRESH_MS);
});

let wasOffline = !navigator.onLine;

setInterval(() => {
    const online = window.Alpine?.store('offline')?.online ?? navigator.onLine;

    if (online && wasOffline) {
        syncAllDrafts();
    }

    wasOffline = !online;
}, 5000);

window.PuntoyaOffline = {
    uuid,
    loadCatalog,
    refreshCatalog,
    loadDrafts,
    getDraft,
    upsertDraft,
    clearDraft,
    syncAllDrafts,
};
