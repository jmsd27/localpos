# Espejo en la nube — guía de despliegue

Esta carpeta documenta cómo conectar una instalación local de LOCALPOS con
un espejo de solo lectura en la nube. No aprovisiona ningún servidor real:
son los pasos a seguir cuando se contrate el VPS/hosting. Ver el plan
completo en `hazy-weaving-wave.md` (arquitectura, decisiones y verificación).

## 1. Requisitos mínimos del VPS

- PHP 8.2+ (el proyecto usa 8.4 en desarrollo) con las extensiones que ya
  pide `composer.json` (mysqli/pdo_mysql, mbstring, etc. — estándar en
  cualquier hosting Laravel).
- MySQL 8.
- HTTPS obligatorio (Let's Encrypt vía el panel del hosting). El
  `X-Sync-Token` viaja como credencial en cada request; sin TLS se
  filtraría en la red pública en cuanto el tráfico salga de la LAN.

## 2. Levantar la instancia "mirror"

1. Clonar este mismo repositorio en el VPS.
2. `composer install --no-dev` (o con dev si se quiere depurar ahí).
3. Copiar `.env.example` a `.env` y configurar:
   - `SYNC_ROLE=mirror`
   - `DB_*` apuntando al MySQL real de la nube (una base de datos nueva,
     vacía).
   - `SYNC_CLOUD_URL` se deja vacío en esta instancia (no aplica: el mirror
     recibe, no envía).
4. `php artisan key:generate`
5. `php artisan migrate`
6. Crear al menos una cuenta para entrar a ver el espejo remotamente:
   ```
   php artisan sync:make-viewer "Dueño" owner@midominio.com "una-contraseña-segura"
   ```
   **Importante:** las cuentas de `users` que llegan sincronizadas desde cada
   sucursal (staff, cajeros, etc.) **no sirven para iniciar sesión** en el
   espejo — su contraseña se reemplaza a propósito por un valor aleatorio en
   cada sincronización (para no exponer los hashes reales ni dejar una
   contraseña adivinable), así que solo existen ahí para que los reportes
   muestren nombres ("atendido por: Ana Cajero"). El acceso real al espejo es
   siempre con una cuenta creada directamente en la nube vía
   `sync:make-viewer`. Cualquier cuenta que inicie sesión en el espejo —
   incluida una recién creada así, sin importar su rol — solo puede ver
   reportes/consultas; nunca puede operar el POS ahí (lo aplica el
   `Gate::before` de `AppServiceProvider`, ver Fase 2 del plan).

## 3. Dar de alta cada sucursal nueva (paso manual, una vez por sucursal)

El primer envío de una sucursal depende de que su propia fila de
`Business`/`Branch` ya exista en la nube (si no, ¿contra qué autentica el
token?). Por eso este paso es manual y va **antes** del primer
`sync:push` de esa sucursal:

1. En la instalación **local** de la sucursal, averiguar sus IDs locales:
   ```
   php artisan tinker
   >>> \App\Models\Business::first()->id   // normalmente 1
   >>> \App\Models\Branch::where('code', 'MTY-01')->first()->id
   ```
2. En el servidor **mirror** (la nube), correr:
   ```
   php artisan sync:provision-branch MTY-01 <local-business-id> <local-branch-id> --branch-name="Sucursal Monterrey"
   ```
   Esto crea el placeholder de `Business`/`Branch` en la nube, genera un
   token y lo imprime en pantalla.
3. En la instalación **local** de esa sucursal, guardar el token impreso:
   ```
   php artisan sync:set-token MTY-01 <token-impreso>
   ```
4. En el `.env` local de esa sucursal, configurar `SYNC_CLOUD_URL` apuntando
   al dominio del mirror (ej. `https://cloud.midominio.com`).

A partir de aquí, `sync:push` (programado cada 5 min) y el worker de cola
(near-real-time) empiezan a enviar todo lo demás automáticamente.

## 4. Tareas Programadas de Windows (lado local, cada sucursal)

### 4.1 Worker de cola (push casi inmediato)

Registrar `scripts\sync-vps\queue-worker.bat` para que corra "al iniciar
sesión" o "al iniciar el equipo":

```
schtasks /create /tn "LocalposQueueWorker" /tr "C:\laragon\www\localpos\scripts\sync-vps\queue-worker.bat" /sc onstart /ru SYSTEM
```

Si `php` no está en el PATH del sistema, define la variable `PHP_BIN` antes
(o edítala directamente en el `.bat`) apuntando al `php.exe` real, ej.
`C:\laragon\bin\php\php-8.4.25-nts-Win32-vs17-x64\php.exe`.

### 4.2 Scheduler de Laravel (catch-up cada 5 min, incluye sync:push)

El proyecto ya depende de que corra `php artisan schedule:run` cada minuto
(esto también dispara `localpos:backup`). Si aún no está registrado:

```
schtasks /create /tn "LocalposScheduler" /tr "php artisan schedule:run --working-dir=C:\laragon\www\localpos" /sc minute /mo 1
```

Con esto, aunque el worker de cola se caiga, `sync:push` sigue corriendo
cada `SYNC_SCHEDULE_MINUTES` (5 por defecto) como red de seguridad — la
nube nunca se queda más de unos minutos desactualizada.

## 5. Qué NO hace este paquete todavía

- No aprovisiona el VPS, DNS ni certificado — eso se hace manualmente (o con
  el panel del hosting elegido) cuando se contrate.
- No hay UI de administración de sucursales todavía; `sync:set-token` y
  `sync:provision-branch` son los comandos de arranque hasta que se
  construya una pantalla dedicada.
- Los íconos de la PWA (`public/icons/*.png`) son un placeholder morado con
  "LP" — reemplazar con el logo real del negocio cuando esté disponible.
