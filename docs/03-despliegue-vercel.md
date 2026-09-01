# 3 · Despliegue del espejo en Vercel

Se hace **una sola vez**. Resultado: `https://mi-espejo.vercel.app` corriendo
el mismo código de LOCALPOS en modo `SYNC_ROLE=mirror`, listo para recibir la
sincronización de todas las sucursales.

## 3.1 Panorama

Vercel no tiene PHP ni MySQL propios. Este repo ya trae lo necesario:

| Archivo | Rol |
|---------|-----|
| `api/index.php` | Entrada serverless; reubica `storage/` a `/tmp` (FS de solo lectura). |
| `vercel.json` | Runtime `vercel-php@0.8.0` (PHP 8.4), ruteo de estáticos, cron diario. |
| `.vercelignore` | Excluye tests, docs y basura local del deploy. |
| `.env.vercel.example` | Plantilla de variables de entorno del espejo. |

Necesitas aparte: **un MySQL 8 gestionado con TLS**.

## 3.2 Crear la base de datos gestionada

Cualquier MySQL 8 con TLS sirve. Recomendado por tener plan gratuito y ser
compatible con el driver `mysql`:

### Opción A — TiDB Cloud Serverless (recomendada)

1. Crea cuenta en <https://tidbcloud.com> › **Create Cluster** › *Serverless*.
2. Región cercana a la de tu proyecto Vercel (ej. `us-east-1`).
3. **Connect** › *Connect With: General* › copia host, puerto (`4000`),
   usuario (`xxxx.root`) y genera password.
4. TiDB exige TLS contra la CA del sistema — en Vercel esa ruta es
   `/etc/pki/tls/certs/ca-bundle.crt` (ya está en `.env.vercel.example`).
5. Crea la base: en el SQL editor de TiDB, `CREATE DATABASE localpos_cloud;`

### Opción B — PlanetScale / Railway / Aiven

Mismo procedimiento: crea la base, copia credenciales, apunta
`MYSQL_ATTR_SSL_CA` a `/etc/pki/tls/certs/ca-bundle.crt`. PlanetScale no
permite foreign keys nativas por defecto — actívalas en la config del branch
o usa el modo compatible.

## 3.3 Crear el proyecto en Vercel

Con la CLI (`npm i -g vercel`, ya instalada):

```powershell
cd C:\laragon\www\localpos
vercel login
vercel link          # crea/enlaza el proyecto; NO despliegues aún
```

En **Vercel › Project › Settings › General**, deja casi todo en automático:
- **Framework Preset:** Other
- **Build Command:** (vacío / *Override* apagado) — `vercel-php` corre
  `composer install` y éste ejecuta el script `vercel` de `composer.json`
  (`npm ci` + `npm run build` + limpieza de `node_modules`).
- **Output Directory:** (vacío)
- **Install Command:** (vacío)
- **Node.js Version:** 22.x

> **Versión de PHP.** `vercel.json` fija `vercel-php@0.8.0` → PHP **8.4** sobre
> Amazon Linux 2023 (la misma línea que se usa en desarrollo). No subas a
> `0.9.0` sin probar: usa PHP 8.5, que Laravel 12 aún no soporta oficialmente.

## 3.4 Variables de entorno

Copia `.env.vercel.example`, rellénalo y cárgalo en
**Settings › Environment Variables** (entorno *Production*). Mínimo:

```
APP_KEY           (genera en local: php artisan key:generate --show)
APP_ENV=production
APP_DEBUG=false
APP_URL           https://TU-PROYECTO.vercel.app
SYNC_ROLE=mirror
DB_CONNECTION=mysql
DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD
MYSQL_ATTR_SSL_CA=/etc/pki/tls/certs/ca-bundle.crt
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
```

Por CLI:

```powershell
vercel env add APP_KEY production
vercel env add DB_HOST production
# ...repite por cada variable
```

## 3.5 Migrar la base de datos de la nube

Vercel no da shell. Corre las migraciones **desde tu equipo apuntando a la
base de la nube**. Usa un `.env` temporal para no tocar tu instalación local:

```powershell
cd C:\laragon\www\localpos
copy .env .env.local.bak

# Edita .env: DB_* -> credenciales de la nube, SYNC_ROLE=mirror,
# MYSQL_ATTR_SSL_CA=  (déjalo vacío en tu equipo: aquí usas la CA de Windows;
# el valor de Vercel solo aplica en Vercel).
php artisan migrate --force

copy .env.local.bak .env   # restaura tu .env local
del .env.local.bak
```

**Alternativa sin tocar `.env`:** define `DEPLOY_KEY` en Vercel y dispara:

```powershell
curl -X POST https://TU-PROYECTO.vercel.app/deploy/migrate ^
  -H "Authorization: Bearer <DEPLOY_KEY>"
```

## 3.6 Crear una cuenta para entrar al espejo

Las cuentas de `users` que llegan sincronizadas **no sirven para login** en el
espejo (su password se randomiza). Crea una cuenta real, otra vez apuntando
tu `.env` a la base de la nube (como en 3.5):

```powershell
php artisan sync:make-viewer "Dueño" dueno@midominio.com "una-contraseña-larga"
```

Cualquier cuenta que entre al espejo solo puede ver reportes/consultas —
nunca operar el POS (lo fuerza `Gate::before`).

## 3.7 Desplegar

```powershell
vercel --prod
```

Verifica:
- `https://TU-PROYECTO.vercel.app/up` → 200.
- La raíz redirige a `/login`; inicia sesión con la cuenta de 3.6.
- El banner morado "Vista de solo lectura — reflejo en la nube" aparece arriba.
- `https://TU-PROYECTO.vercel.app/menu` carga el menú público (vacío hasta que
  sincronice la primera sucursal).

## 3.8 Dominio propio (opcional)

**Settings › Domains › Add** `espejo.midominio.com`, crea el CNAME que indica
Vercel, y actualiza `APP_URL`. El HTTPS lo emite Vercel solo.

## 3.9 Siguiente paso

Conecta la primera sucursal → [documento 4](04-alta-de-sucursales.md).

## 3.10 Problemas comunes

| Síntoma | Causa / arreglo |
|---------|-----------------|
| `SQLSTATE[HY000] [2002]` o timeout de DB | `DB_HOST/PORT` mal, o falta `MYSQL_ATTR_SSL_CA`. TiDB usa puerto `4000`. |
| `SSL connection error` | Ruta de CA incorrecta. En Vercel: `/etc/pki/tls/certs/ca-bundle.crt`. |
| 500 con `file_put_contents .../storage/...` | `api/index.php` no se está usando. Revisa `vercel.json` › `routes`. |
| CSS/JS sin cargar | El script `vercel` de `composer.json` no corrió. Revisa el log de build de Vercel; debe verse `npm run build` y `public/build/manifest.json` generado. |
| Redirige a `http://` / sesión no persiste | Falta `trustProxies` (ya está) o `APP_URL` con `http://`. Debe ser `https://`. |
| 419 Page Expired al iniciar sesión | `APP_KEY` distinto entre deploys, o `SESSION_SECURE_COOKIE` sin HTTPS. |
| El cron no corre | En plan Hobby, Vercel Cron corre máx. 1 vez/día — por eso está a `0 8 * * *`. La sincronización NO depende de este cron. |
