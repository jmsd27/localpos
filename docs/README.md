# Manuales de implementación — LOCALPOS

LOCALPOS opera **100% local** en cada sucursal (Laravel + MySQL sobre
Laragon, servido por LAN, sin depender de Internet para vender). Además
mantiene un **espejo de solo lectura en la nube** (desplegado en Vercel) que
recibe una copia de los datos para consultarlos de forma remota y desde una
PWA instalable en el celular.

```
┌──────────────────────────┐            cada 15 min (o casi al instante        ┌───────────────────────────┐
│  SUCURSAL (local)        │            si el worker de cola está activo)      │  ESPEJO EN LA NUBE        │
│  Laragon + PHP + MySQL   │  ───────────  POST /api/sync/ingest  ──────────▶  │  Vercel + MySQL gestionado│
│  SYNC_ROLE=source        │            X-Sync-Token por sucursal              │  SYNC_ROLE=mirror         │
│  Opera el POS, caja, KDS │                                                   │  Solo consulta / reportes │
│  Fuente de verdad        │                                                   │  Menú QR público          │
└──────────────────────────┘                                                   └───────────────────────────┘
```

## Orden de lectura

| # | Documento | Cuándo |
|---|-----------|--------|
| 1 | [Arquitectura](01-arquitectura.md) | Para entender qué se sincroniza y por qué. Léelo una vez. |
| 2 | [Implementación local](02-implementacion-local.md) | Al montar LOCALPOS en la PC de una sucursal nueva. |
| 3 | [Despliegue en Vercel](03-despliegue-vercel.md) | Una sola vez, para crear el espejo en la nube. |
| 4 | [Alta de sucursales en el espejo](04-alta-de-sucursales.md) | Cada vez que conectes una sucursal al espejo. |
| 5 | [Operación y diagnóstico](05-operacion-y-diagnostico.md) | Día a día: revisar salud del sync, resolver atascos, respaldos. |
| 6 | [Checklist de lanzamiento](06-checklist-lanzamiento.md) | Antes de dar por vivo el espejo. |

## Archivos de configuración relacionados

- `.env.example` — base para la instalación **local** (`SYNC_ROLE=source`).
- `.env.vercel.example` — base para el **espejo** en la nube (`SYNC_ROLE=mirror`).
- `config/sync.php` — qué modelos se sincronizan, mapa de foreign keys, cadencia.
- `vercel.json` / `api/index.php` — cómo corre Laravel dentro de Vercel.
- `scripts/sync-vps/` — scripts de Windows (worker de cola, scheduler) y notas.
