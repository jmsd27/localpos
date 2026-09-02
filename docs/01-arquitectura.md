# 1 · Arquitectura del espejo en la nube

## Principio

**Local es siempre la fuente de verdad.** Nadie opera el POS contra la nube.
El espejo (`SYNC_ROLE=mirror`) solo sirve para *ver* — reportes, historial,
dashboard, menú QR público — y bloquea toda acción de escritura vía
`Gate::before` en `AppServiceProvider`, además de mostrar un banner
"Vista de solo lectura".

El mismo código corre en los dos roles; la variable `SYNC_ROLE` decide el
comportamiento.

## Flujo de datos (una dirección: local → nube)

1. **Captura.** `SyncOutboxObserver` está registrado sobre todos los modelos
   de `config('sync.models')`. Cada `create`/`update`/`delete` inserta una
   fila en `sync_outbox` con un snapshot JSON de la fila.
2. **Envío.** Dos caminos, mismo `SyncPushService`:
   - **Casi inmediato:** `PushSyncBatchJob` se encola tras cada escritura.
     Requiere que el worker de cola (`queue:work`) esté corriendo.
   - **Red de seguridad:** el comando `sync:push`, programado cada
     `SYNC_SCHEDULE_MINUTES` (15 por defecto). Funciona aunque el worker
     esté caído.
3. **Autenticación.** Cada lote viaja a `POST {SYNC_CLOUD_URL}/api/sync/ingest`
   con el header `X-Sync-Token` (un token único por sucursal). HTTPS
   obligatorio — el token es una credencial.
4. **Ingesta.** En el espejo, `SyncIngestionService` aplica el lote dentro de
   una transacción, en orden (padres antes que hijos). Escribe con el query
   builder crudo, no Eloquent: el espejo es una copia fiel, así que conserva
   los `created_at`/`updated_at` de origen y los campos JSON tal cual (sin
   casts que los doble-codificarían), y no dispara eventos de modelo.

## Identidad entre sucursales

Cada sucursal tiene su propio MySQL con IDs autoincrementales propios. El
"pedido 1" de `MTY-01` y el "pedido 1" de `CDMX-02` chocarían en la nube.

Solución: `branches.code` (asignado a mano: `MTY-01`, `CDMX-02`, …) es el
ancla global. La tabla `sync_id_map` traduce
`(branch_code, model_type, local_id) → cloud_id`. La nube genera sus propios
IDs; las foreign keys del payload se reescriben con ese mapa usando
`config('sync.fk_map')` (mapa explícito por modelo, nunca adivinado por
nombre de columna).

Si llega un hijo antes que su padre (entrega fuera de orden, reintento), esa
entrada se **difiere** sin romper el resto del lote y se reintenta en el
siguiente push.

## Qué se sincroniza

Todo lo listado en `config('sync.models')`: negocio, sucursales, catálogo,
clientes, proveedores, mesas, estaciones, insumos y recetas, terminales,
cajas y sesiones, **pedidos con ítems / modificadores / pagos /
cancelaciones**, movimientos de caja e inventario, compras, y `audit_logs`.

**Usuarios:** se sincronizan completos **excepto** `password`, `pin_hash` y
`remember_token`, que se reemplazan por un valor aleatorio por fila. Sirven
para mostrar nombres en los reportes ("atendido por: Ana"), **no** para
iniciar sesión en el espejo. El acceso al espejo se crea aparte con
`php artisan sync:make-viewer`.

**Imágenes subidas (logo del negocio):** el archivo físico no se replica a
Vercel (no hay disco persistente). El reporte funciona igual; si necesitas el
logo en el espejo, configura un disco S3 y `FILESYSTEM_DISK=s3` en ambos
lados (fuera del alcance de esta entrega).

**Referencias polimórficas** (`audit_logs.auditable_id`,
`inventory_movements.reference_id`): se reescriben con `config('sync.morph_map')`
— la ingesta busca la clave del modelo por el FQCN de la columna `*_type` y
traduce el id vía `sync_id_map`. Es *best-effort*: si el tipo no se sincroniza
o el referido aún no llegó, el id local se deja tal cual (no se difiere la
entrada, porque estas filas son informativas y su referido puede haberse
borrado o preceder al backfill).

## Cobertura de pruebas

`tests/Feature/Cloud/SyncEngineTest.php` cubre el motor de punta a punta:
captura en el outbox (incluida la resolución de sucursal vía relación y el
blanqueo de campos sensibles), identidad cruzada entre sucursales,
idempotencia, diferimiento padre-antes-que-hijo, fidelidad de `created_at` y
JSON en el espejo, autenticación del endpoint por token, y el bloqueo de
escritura del `Gate::before` (incluido que gana sobre el callback de
spatie/laravel-permission — por eso se registra en `register()`, no en
`boot()`).

## Por qué Vercel necesita adaptación

| Restricción de Vercel | Cómo se resuelve |
|-----------------------|------------------|
| No corre PHP nativamente | Runtime comunitario `vercel-php@0.8.0` → PHP 8.4 (`vercel.json`) |
| Sistema de archivos de solo lectura (salvo `/tmp`) | `api/index.php` reubica `storage/` a `/tmp` |
| Sin MySQL | MySQL gestionado externo (TiDB Cloud / PlanetScale / Railway) |
| Sin proceso de cron persistente | `crons` en `vercel.json` → `GET /cron/housekeeping` |
| Detrás de un proxy | `trustProxies('*')` en `bootstrap/app.php` |
| Sesiones/caché/colas sin disco | Ya van a base de datos (`SESSION_DRIVER=database`, etc.) |

La cadencia de replicación (**cada 15 min**) la maneja el **scheduler local**
de cada sucursal, no Vercel. El espejo es pasivo: solo recibe.
