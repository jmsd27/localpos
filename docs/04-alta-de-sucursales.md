# 4 · Conectar una sucursal al espejo

Se repite **una vez por sucursal**. Antes de empezar necesitas:

- El espejo ya desplegado ([documento 3](03-despliegue-vercel.md)).
- La sucursal ya instalada y operando en local ([documento 2](02-implementacion-local.md)),
  con su `code` definido (ej. `MTY-01`).
- Un checkout de LOCALPOS en tu equipo con un `.env` que puedas apuntar
  temporalmente a la **base de datos de la nube** (lo llamamos "consola de la
  nube" — es el mismo truco de `.env` del documento 3 §3.5).

## 4.1 Por qué hay un paso manual

La fila `businesses`/`branches` de la nube es, ella misma, un dato
sincronizado. Pero `AuthenticateSyncToken` necesita encontrar esa fila desde
el **primer** request de esa sucursal. Huevo y gallina. `sync:provision-branch`
crea el placeholder y su token antes del primer `sync:push`.

## 4.2 Paso 1 — IDs locales de la sucursal

En la **PC de la sucursal**:

```powershell
php artisan tinker
>>> \App\Models\Business::first()->id            // normalmente 1
>>> \App\Models\Branch::where('code','MTY-01')->first()->id
```

Anota `local-business-id` y `local-branch-id`.

## 4.3 Paso 2 — Provisionar en la nube

Desde la **consola de la nube** (tu `.env` apuntando a la base de la nube,
`SYNC_ROLE=mirror`):

```powershell
php artisan sync:provision-branch MTY-01 <local-business-id> <local-branch-id> ^
  --business-name="Mi Negocio" ^
  --branch-name="Sucursal Monterrey"
```

Imprime un **token**. Cópialo.

> Si vas a conectar varias sucursales del mismo negocio, en la segunda y
> siguientes omite `--business-name` (el negocio ya existe en la nube).

## 4.4 Paso 3 — Guardar el token en la sucursal

En la **PC de la sucursal**:

```powershell
php artisan sync:set-token MTY-01 <token-impreso>
```

Y en su `.env`:

```
SYNC_CLOUD_URL=https://mi-espejo.vercel.app
```

Limpia config si la tenías cacheada: `php artisan config:clear`.

## 4.5 Paso 4 — Carga inicial (backfill)

La sucursal ya tenía datos antes de conectarse. Encólalos:

```powershell
php artisan sync:backfill
php artisan sync:push
```

`sync:push` enviará en lotes de 200. Repite `sync:push` hasta que
`Pendientes: 0`, o simplemente espera — el scheduler lo corre cada 15 min.

## 4.6 Verificación

En la **PC de la sucursal**:

```powershell
php artisan tinker
>>> \App\Models\SyncOutboxEntry::whereNull('synced_at')->count();   // debe ir a 0
>>> \App\Models\Branch::first()->last_synced_at;                    // fecha reciente
```

En el **espejo** (navegador, con la cuenta de `sync:make-viewer`):
- Reportes / Historial de ventas muestran los datos de la sucursal.
- El menú `/menu` ya lista productos.

Prueba en caliente: crea una venta nueva en la sucursal. Con el worker de cola
activo aparece en el espejo en segundos; sin él, en el siguiente tick de
`sync:push` (≤ 15 min).

## 4.7 Choque de IDs entre sucursales — comprobación

Si conectas `MTY-01` y `CDMX-02`, y ambas tienen un "pedido 1":

```sql
-- en la base de la nube
SELECT branch_code, model_type, local_id, cloud_id
FROM sync_id_map WHERE model_type='order' AND local_id=1;
```

Deben salir **dos filas**, distinto `branch_code` y distinto `cloud_id`. Cada
pedido vive separado en la nube. Eso confirma que la identidad cruzada
funciona.
