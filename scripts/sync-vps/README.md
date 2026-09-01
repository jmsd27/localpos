# scripts/sync-vps — worker de cola y scheduler (lado local)

> **El espejo en la nube ahora se despliega en Vercel, no en un VPS.**
> La guía completa está en [`docs/`](../../docs/README.md):
> - Despliegue del espejo → [`docs/03-despliegue-vercel.md`](../../docs/03-despliegue-vercel.md)
> - Conectar una sucursal → [`docs/04-alta-de-sucursales.md`](../../docs/04-alta-de-sucursales.md)
>
> El nombre de esta carpeta se conserva por compatibilidad; su contenido
> aplica a la **PC de la sucursal** (Windows), no a la nube.

Esta carpeta solo contiene el supervisor del worker de cola para Windows.

## Sincronización de dos niveles

1. **Casi inmediato** — `PushSyncBatchJob` se encola tras cada escritura.
   Necesita un `queue:work` vivo → `queue-worker.bat` (abajo).
2. **Red de seguridad** — `sync:push` programado **cada 15 minutos**
   (`SYNC_SCHEDULE_MINUTES`, ver `config/sync.php` y `routes/console.php`).
   Funciona aunque el worker esté caído.

Si el worker se cae, la sincronización no se detiene: solo se degrada de
"segundos" a "≤ 15 min".

## 1. Scheduler de Laravel — obligatorio

`php artisan schedule:run` debe correr **cada minuto**. Dispara
`localpos:backup`, `localpos:housekeeping` y `sync:push`.

```
schtasks /create /tn "LocalposScheduler" ^
  /tr "C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe C:\laragon\www\localpos\artisan schedule:run" ^
  /sc minute /mo 1 /ru SYSTEM
```

## 2. Worker de cola — opcional (recomendado)

Registrar `queue-worker.bat` "al iniciar el equipo":

```
schtasks /create /tn "LocalposQueueWorker" ^
  /tr "C:\laragon\www\localpos\scripts\sync-vps\queue-worker.bat" ^
  /sc onstart /ru SYSTEM
```

Si `php` no está en el PATH del sistema, edita `PHP_BIN` dentro del `.bat`
apuntando al `php.exe` real de Laragon, p. ej.
`C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe`.

## 3. Alta de sucursal en el espejo (resumen)

Detalle en [`docs/04-alta-de-sucursales.md`](../../docs/04-alta-de-sucursales.md).

1. Consola de la nube: `php artisan sync:provision-branch MTY-01 <local-biz-id> <local-branch-id> --branch-name="..."` → imprime token.
2. PC de la sucursal: `php artisan sync:set-token MTY-01 <token>` y `SYNC_CLOUD_URL=https://mi-espejo.vercel.app` en `.env`.
3. PC de la sucursal: `php artisan sync:backfill && php artisan sync:push`.

## 4. Notas / pendientes

- Los íconos de la PWA (`public/icons/*.png`) son un placeholder morado con
  "LP" — reemplázalos con el logo real cuando esté disponible.
- Las imágenes subidas (logo del negocio) **no** se replican a Vercel (no hay
  disco persistente). Para tenerlas en el espejo haría falta un disco S3
  compartido en ambos lados.
