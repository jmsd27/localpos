# LOCALPOS — guía para Claude

Punto de venta para restaurantes/bares. **Opera 100% local por sucursal** (Laravel + MySQL
servido por LAN, sin dependencia de internet para vender) y además empuja un **espejo de solo
lectura a la nube** casi en tiempo real.

## Stack

- Laravel 12 + Livewire 4 (con Volt SFC) + Tailwind v4, sobre Laragon.
- PHP 8.4 en `C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64` (no está en el PATH por defecto).
- MySQL 8 en desarrollo/producción local; **SQLite `:memory:` en los tests** (ver `phpunit.xml`).
- El espejo de la nube corre el mismo código en Vercel (`vercel-php@0.8.0`, PHP 8.4).

## Comandos

```bash
# Prefija el PATH de PHP en cada shell:
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"

composer test              # config:clear + php artisan test (SQLite en memoria)
php artisan test --filter=X # un subconjunto
composer dev               # serve + queue:listen + pail + vite, todo junto
npm run build              # assets de producción
```

`php artisan test` = suite completa en verde (~140 pruebas). La primera prueba tarda bastante
(construye el esquema por migraciones); las demás son rápidas.

## Arquitectura

- **Un código, dos roles** vía `SYNC_ROLE` (`source` = local, fuente de verdad; `mirror` = nube,
  solo lectura). El `mirror` bloquea toda ability que no sea de lectura con un `Gate::before`
  registrado en `AppServiceProvider::register()` (no `boot()` — tiene que quedar antes del
  `Gate::before` de spatie/laravel-permission, que devuelve `true` en cuanto el usuario tiene el
  permiso) y muestra un banner ámbar en `layouts/app.blade.php`.
- **Captura de cambios**: `SyncOutboxObserver` (genérico, registrado en `AppServiceProvider::boot()`
  para cada modelo de `config/sync.php`) escribe en `sync_outbox`. `config/sync.php` es la única
  fuente de verdad de qué se sincroniza (`models`, `fk_map`, `branch_via`, `exclude_fields`).
- **Envío**: `PushSyncBatchJob` (cola `database`, casi inmediato si el worker corre) +
  `sync:push` programado como red de seguridad. Ambos usan `SyncPushService`.
- **Ingesta** (lado nube): `POST /api/sync/ingest` con `X-Sync-Token` por sucursal →
  `SyncIngestionService` + tabla `sync_id_map` (identidad cruzada por `branches.code`).
- **Identidad entre sucursales**: cada fila se identifica por `(branch_code, model_type, local_id)`.
  La nube tiene sus propios IDs autoincrementales; `sync_id_map` traduce las FK.
- Datos sensibles: `password` / `pin_hash` / `remember_token` se randomizan por fila en el payload.

## Convenciones

- **Rutas**: `Route::livewire('/ruta', 'modulo.componente')` en `routes/web.php`, con
  `->middleware('permission:recurso.accion')`.
- **Permisos** (`spatie/laravel-permission`, ver `database/seeders/PermissionSeeder.php`):
  `recurso.ver` / `.ver_movimientos` / `.ver_kardex` = lectura; `.crear` / `.editar` / `.eliminar`
  / `.abrir` / `.cerrar` / `.ajustar` / `.anular` = escritura. El `mirror` usa esta convención
  para decidir qué bloquear.
- **Vistas**: Volt single-file, prefijo `⚡`, en `resources/views/components/<módulo>/⚡nombre.blade.php`.
  Layout staff: `layouts/app.blade.php` (sidebar violeta, marca = heroicon `building-storefront`).
- **Lógica de negocio** en `app/Services/*` (`SaleService`, `CashRegisterService`,
  `InventoryService`, `KitchenService`, `PurchaseService`, `PrintService`, `ReportService`,
  `BackupService`, `SettingsService`, `AuditLogger`, `FolioGenerator`). Los componentes Livewire
  no reimplementan reglas: llaman al Service.
- **Auditoría**: `AuditLogger` registra en `audit_logs` (con `before`/`after` JSON, IP, UA).
- Sin borrados físicos de datos transaccionales; las cancelaciones son cambio de estado. Solo
  `Product` usa `SoftDeletes`.
- **Impresión**: ESC/POS vía cola (`print_jobs`) que un agente Node (`scripts/print-agent/`)
  consume con `X-Terminal-Token`. Mismo patrón token-por-dispositivo que el sync.

## Comandos Artisan propios

`sync:push`, `sync:backfill`, `sync:provision-branch` (solo mirror), `sync:set-token` (solo
source), `sync:make-viewer` (solo mirror), `localpos:housekeeping`, `localpos:backup`.

## Gotchas

- El repo **no tiene remoto git** — no se puede `git push` hasta configurar uno.
- Vercel tiene FS de solo lectura: `api/index.php` reubica `storage/` a `/tmp`. No subir
  `vercel-php` a 0.9.0 (= PHP 8.5, no soportado por Laravel 12).
- El logo del negocio subido (`businesses.logo_path`, disco local) **no se replica a Vercel** —
  haría falta un disco S3 compartido en ambos lados.
- Las FKs polimórficas (`audit_logs.auditable_id`, `inventory_movements.reference_id`) se
  reescriben *best-effort* vía `config('sync.morph_map')`; si el tipo no se sincroniza o el
  referido no llegó, el id local se deja intacto (no se difiere la entrada).
- El motor de sync tiene cobertura en `tests/Feature/Cloud/SyncEngineTest.php` — corre esos
  tests si tocas `Sync*Service`, `SyncOutboxObserver`, `config/sync.php` o el `Gate::before`.
- Íconos PWA (`public/icons/*.png`) son la marca de la app (violeta + storefront), no un logo
  del negocio.
- Documentación operativa del espejo en la nube en `docs/` (`01-arquitectura` … `06-checklist-lanzamiento`).
- **Manuales de onboarding de un negocio nuevo**: archivos Markdown en `resources/manuales/*.md`,
  renderizados in-app en **Administración → Manuales** (`Route::livewire('/manuales', 'admin.manuales.index')`,
  middleware `role:super-admin`). El avance de lectura se guarda por negocio en `settings` (grupo
  `onboarding`, key `manual:<slug>`). Para agregar/editar un manual basta tocar el `.md` (el índice
  se arma solo por orden de nombre de archivo y el primer `# heading`).
