# 2 · Implementación local (una sucursal)

LOCALPOS corre en la PC de la sucursal sobre **Laragon** (Apache + PHP 8.2+ +
MySQL 8). Esta PC no necesita Internet para vender; solo lo usa para empujar
al espejo en la nube cada 15 minutos.

## 2.1 Requisitos

- Windows 10/11.
- [Laragon](https://laragon.org/) (full) — trae PHP, MySQL, Apache, Composer, Node.
- Git.
- Impresora térmica ESC/POS de red (puerto 9100) si se imprimen tickets/comandas.

## 2.2 Instalación

```powershell
cd C:\laragon\www
git clone <repo-url> localpos
cd localpos

# Dependencias
composer install
npm ci
npm run build

# Configuración
copy .env.example .env
php artisan key:generate

# Base de datos: crea el esquema "localpos" en MySQL (Laragon > Menú > MySQL)
# y ajusta DB_DATABASE / DB_USERNAME / DB_PASSWORD en .env si hace falta.
php artisan migrate
```

Deja el `.env` con `SYNC_ROLE=source` (es el valor por defecto). No configures
`SYNC_CLOUD_URL` todavía — eso se hace en el [documento 4](04-alta-de-sucursales.md).

## 2.3 Primer arranque

Abre `http://localpos.test` (Laragon crea el vhost solo). La raíz redirige a
`/instalacion`: un asistente que crea el negocio, la primera sucursal, los
roles/permisos y la cuenta de administrador, y deja la sesión iniciada.

> **Código de sucursal.** Asígnalo a mano y que sea único entre TODAS las
> sucursales del negocio: `MTY-01`, `MTY-02`, `CDMX-01`, … Si el asistente no
> lo pide, edítalo después en Admin › Configuración o vía tinker
> (`Branch::first()->update(['code' => 'MTY-01'])`). El espejo en la nube usa
> este código como identidad global; dos sucursales con el mismo código
> corromperían datos.

## 2.4 Tareas Programadas de Windows

Dos tareas. Detalle y comandos exactos en
[`scripts/sync-vps/README.md`](../scripts/sync-vps/README.md).

### a) Scheduler de Laravel — **obligatorio**

Ejecuta `php artisan schedule:run` **cada minuto**. Dispara:
- `localpos:backup` (respaldo diario 03:00),
- `localpos:housekeeping` (poda diaria 03:30),
- `sync:push` **cada 15 min** (red de seguridad de la sincronización).

```powershell
schtasks /create /tn "LocalposScheduler" ^
  /tr "C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe C:\laragon\www\localpos\artisan schedule:run" ^
  /sc minute /mo 1 /ru SYSTEM
```

### b) Worker de cola — opcional (recomendado)

Hace que el espejo se actualice **en segundos** en vez de esperar al tick de
15 min. Si esta tarea se cae, no pasa nada grave: `sync:push` sigue cubriendo
cada 15 min.

```powershell
schtasks /create /tn "LocalposQueueWorker" ^
  /tr "C:\laragon\www\localpos\scripts\sync-vps\queue-worker.bat" ^
  /sc onstart /ru SYSTEM
```

Si `php` no está en el PATH del sistema, define `PHP_BIN` dentro del `.bat`
apuntando al `php.exe` real de Laragon.

## 2.5 Agente de impresión (si hay impresora)

`scripts/print-agent/agent.js` corre con Node como proceso aparte, habla por
LAN con este servidor y por red con la impresora (puerto 9100). Configúralo
con las variables `LOCALPOS_URL`, `LOCALPOS_TERMINAL_TOKEN` (Admin ›
Terminales › Regenerar token) y `PRINTER_HOST`. Regístralo también como Tarea
Programada "al iniciar".

## 2.6 Verificación

```powershell
php artisan test          # 136 pruebas en verde
php artisan schedule:list # sync:push debe aparecer como */15
php artisan about         # confirma driver de sesión/caché/cola = database
```

Crea una venta de prueba y confirma que se registran filas en `sync_outbox`:

```powershell
php artisan tinker
>>> \App\Models\SyncOutboxEntry::latest('id')->take(5)->get(['model_type','operation','branch_id','synced_at']);
```

Todavía tendrán `synced_at = null` (aún no hay nube conectada). Eso es
correcto — sigue en el [documento 4](04-alta-de-sucursales.md).
