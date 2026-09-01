# 6 · Checklist de lanzamiento

## Espejo en la nube (una vez)

- [ ] MySQL gestionado creado, base `localpos_cloud` vacía, TLS activo.
- [ ] Proyecto Vercel enlazado (`vercel link`), Framework Preset = Other.
- [ ] Variables de entorno cargadas en *Production* (checklist en `.env.vercel.example`):
  - [ ] `APP_KEY` (generada, no la de local necesariamente — cualquiera válida y estable).
  - [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`.
  - [ ] `SYNC_ROLE=mirror`.
  - [ ] `DB_*` + `MYSQL_ATTR_SSL_CA=/etc/pki/tls/certs/ca-bundle.crt`.
  - [ ] `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`.
  - [ ] `LOG_CHANNEL=stderr`.
- [ ] `php artisan migrate --force` corrido contra la base de la nube.
- [ ] `sync:make-viewer` — al menos una cuenta de acceso al espejo.
- [ ] `vercel --prod` desplegado.
- [ ] `/up` responde 200.
- [ ] Login OK con la cuenta viewer; banner "Vista de solo lectura" visible.
- [ ] Intentar una acción de escritura en el espejo → rechazada.
- [ ] (Opcional) Dominio propio añadido y `APP_URL` actualizado.
- [ ] PWA: en el celular, "Agregar a pantalla de inicio" funciona; sin errores
      de service worker en consola.

## Por cada sucursal

- [ ] LOCALPOS instalado, `php artisan test` en verde.
- [ ] `branches.code` único asignado (`MTY-01`, …).
- [ ] Tarea Programada `LocalposScheduler` (cada minuto) creada y verificada
      (`schtasks /query /tn LocalposScheduler`).
- [ ] (Opcional) Tarea `LocalposQueueWorker` creada.
- [ ] `sync:provision-branch` corrido en la consola de la nube → token obtenido.
- [ ] `sync:set-token` corrido en la sucursal.
- [ ] `SYNC_CLOUD_URL` puesto en el `.env` de la sucursal + `config:clear`.
- [ ] `sync:backfill` + `sync:push` → `Pendientes: 0`.
- [ ] `branches.last_synced_at` con fecha reciente.
- [ ] Venta de prueba aparece en el espejo (segundos con worker, ≤ 15 min sin él).
- [ ] Agente de impresión configurado y probado (si hay impresora).

## Verificación multi-sucursal (si aplica)

- [ ] Dos sucursales con un mismo `local_id` para el mismo modelo →
      `sync_id_map` tiene dos filas distintas, sin choque.
- [ ] Reportes del espejo separan correctamente por sucursal.

## Post-lanzamiento (primera semana)

- [ ] Revisar `SyncOutboxEntry::whereNull('synced_at')->where('attempts','>',0)`
      en cada sucursal — debe estar vacío o casi.
- [ ] Revisar Runtime Logs de Vercel — sin errores recurrentes de ingesta.
- [ ] Confirmar que `localpos:backup` generó `.sql` cada día.
- [ ] Confirmar que el consumo de la base gestionada está dentro del plan.
