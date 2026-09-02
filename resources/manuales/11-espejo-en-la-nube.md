# 11 · Espejo en la nube (opcional)

Una copia **de solo lectura** del sistema, en la nube (Vercel), para ver
reportes, ventas y auditoría en remoto o desde el celular como PWA. **La
operación real nunca ocurre aquí**; local siempre es la fuente de verdad.

Si el negocio no necesita acceso remoto, **puedes saltarte este manual**.

## Cómo funciona

- Cada escritura local (venta, movimiento, ajuste…) se registra en una
  bandeja (`sync_outbox`) y se empuja al espejo: casi al instante si el
  worker de cola corre, y como red de seguridad cada 15 min con `sync:push`.
- El espejo bloquea toda escritura y muestra un aviso "Vista de solo lectura".
- Las contraseñas y PIN **no** viajan al espejo.

## Lo que necesitas (una vez)

1. **MySQL 8 gestionado** con TLS (el proyecto recomienda TiDB Cloud
   Serverless).
2. Cuenta de **Vercel** y el proyecto enlazado (`vercel link`).
3. Variables de entorno del espejo cargadas en Vercel — plantilla en
   `.env.vercel.example` (`SYNC_ROLE=mirror`, `DB_*`, sesión/caché/cola en
   base de datos, etc.).
4. `php artisan migrate --force` contra la base de la nube.
5. `php artisan sync:make-viewer` en la nube para crear una cuenta de acceso
   de solo lectura.
6. `vercel --prod`.

## Por cada sucursal

1. En la nube: `php artisan sync:provision-branch {codigo} {local-biz-id} {local-branch-id}` → devuelve un **token**.
2. En la sucursal local: `php artisan sync:set-token {codigo} {token}`.
3. En el `.env` local: `SYNC_CLOUD_URL=https://tu-espejo.vercel.app` y
   `php artisan config:clear`.
4. `php artisan sync:backfill` para subir los datos que ya existían, luego
   `php artisan sync:push`. Debe quedar en `Pendientes: 0`.
5. Programa la **Tarea Programada** de Windows del scheduler (cada minuto) y,
   opcionalmente, el worker de cola.

## Documentación detallada

El paso a paso completo está en la carpeta `docs/` del proyecto:

- `docs/03-despliegue-vercel.md` — despliegue del espejo.
- `docs/04-alta-de-sucursales.md` — provisión de cada sucursal.
- `docs/05-operacion-y-diagnostico.md` — qué revisar si algo no sincroniza.
- `docs/06-checklist-lanzamiento.md` — checklist marcable.

## Verificación

- `/up` del espejo responde 200.
- Login con la cuenta *viewer*; se ve el banner "Vista de solo lectura".
- Intentar una acción de escritura en el espejo → rechazada.
- Una venta de prueba en local aparece en el espejo (segundos con worker,
  ≤ 15 min sin él); `branches.last_synced_at` con fecha reciente.

Continúa con el **manual 12**.
