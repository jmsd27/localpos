# 5 · Operación y diagnóstico

## 5.1 Salud de la sincronización (por sucursal)

```powershell
php artisan tinker
>>> \App\Models\SyncOutboxEntry::whereNull('synced_at')->count();      // backlog
>>> \App\Models\SyncOutboxEntry::whereNull('synced_at')->where('attempts','>',0)
...     ->latest('id')->take(10)->get(['model_type','model_id','attempts','last_error']);
>>> \App\Models\Branch::first()->only(['code','last_synced_at']);
```

Interpretación:

| Observación | Significado | Acción |
|-------------|-------------|--------|
| backlog crece y `attempts=0` | El worker de cola no corre y aún no llega el tick de 15 min | Normal si es < 15 min. Si no, revisa la Tarea Programada `LocalposScheduler`. |
| backlog con `attempts>0` y `last_error` de red | El espejo no responde | Revisa `SYNC_CLOUD_URL`, que Vercel esté arriba, y `/up`. |
| `last_error` = "HTTP 401" | Token inválido | Repite [documento 4](04-alta-de-sucursales.md) §4.3–4.4. |
| `last_error` = "HTTP 404" | El espejo no está en `SYNC_ROLE=mirror` | Corrige la env var en Vercel y redeploy. |
| `last_error` menciona una FK / "dependencia" | Llegó un hijo antes que el padre | Se reintenta solo. Si persiste, corre `sync:backfill` del modelo padre. |

Forzar un envío ahora: `php artisan sync:push`.

## 5.2 Logs del espejo (Vercel)

**Vercel › Project › Logs** (Runtime Logs). Con `LOG_CHANNEL=stderr` todo el
log de Laravel sale ahí. Filtra por `sync:ingest` para ver rechazos de
ingesta.

## 5.3 Reprocesar / reenviar

- **Reenviar todo lo no confirmado:** ya es automático — `sync:push` solo mira
  filas con `synced_at = null`.
- **Re-encolar una fila concreta:**
  `SyncOutboxEntry::find($id)->update(['synced_at' => null, 'attempts' => 0]);`
- **Volver a mandar un modelo entero desde cero:** `php artisan sync:backfill <modelo>`
  (ej. `product`). Es idempotente en la nube (upsert por `sync_id_map`), no
  duplica.

## 5.4 Respaldos

- **Local:** `localpos:backup` corre diario 03:00 (requiere `MYSQLDUMP_PATH`
  correcto en `.env`). Los `.sql` van a `storage/app/backups`, retención
  `BACKUP_RETENTION` días. Descarga desde Admin › Respaldos.
- **Nube:** el respaldo lo da tu proveedor de MySQL gestionado (TiDB/PlanetScale
  lo hacen automático). El espejo es reconstruible: si se pierde, crea una base
  nueva, migra, y corre `sync:backfill` + `sync:push` en cada sucursal.

## 5.5 Housekeeping

`localpos:housekeeping` (local: diario 03:30; nube: cron de Vercel diario)
poda sesiones expiradas, trabajos fallidos > 14 días y outbox ya sincronizado
fuera de `SYNC_OUTBOX_RETENTION_DAYS`. Correr a mano: `php artisan localpos:housekeeping`.

## 5.6 Actualizar el código

**Local (cada sucursal):**
```powershell
git pull
composer install
npm ci && npm run build
php artisan migrate --force
php artisan config:clear
```
Reinicia el worker de cola si corre como tarea.

**Nube:**
```powershell
vercel --prod           # redeploy
# si el pull traía migraciones nuevas, córrelas contra la base de la nube
# (documento 3 §3.5) ANTES o justo después del deploy.
```

## 5.7 Cambiar la cadencia de 15 min

En el `.env` **local** de la sucursal: `SYNC_SCHEDULE_MINUTES=5` (o 10, 30, 60).
`routes/console.php` lo traduce a la frecuencia soportada más cercana.
`php artisan config:clear` y listo. El espejo no necesita cambios.

## 5.8 Qué hacer si una sucursal se queda sin Internet

Nada. Sigue vendiendo 100% normal. El `sync_outbox` se acumula y, al volver la
conexión, `sync:push` (o el worker) drena el backlog en orden. La única
consideración: `SYNC_OUTBOX_RETENTION_DAYS` solo poda filas **ya
sincronizadas**, así que un backlog largo nunca se pierde.
