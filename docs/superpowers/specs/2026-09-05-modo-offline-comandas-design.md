# Modo offline de operación para Bar La Martina (instancia sin servidor local)

Fecha: 2026-09-05
Estado: aprobado en chat, pendiente de convertir en plan de implementación.

## Contexto

Bar La Martina va a operar el sistema **solo desde el dominio** (sin ninguna
compu/servidor local en el bar). Eso significa que, a diferencia del resto de
instalaciones de puntoYA (que corren 100% local por LAN y solo empujan un
espejo de solo lectura a la nube), acá **el internet del bar es la única vía
para vender**, y ya se confirmó que los cortes pueden durar minutos u horas y
no hay respaldo de conectividad (router 4G, etc.) previsto.

Esta instancia va a correr con `SYNC_ROLE=source` de verdad (no `mirror`) en
el hosting del dominio — es una instalación normal, con base de datos propia;
lo único distinto es que sus usuarios la usan por internet en vez de por LAN.

## Decisión de arquitectura

Se evaluaron dos caminos (ver discusión previa en el chat de esta sesión):

1. **Servidor local barato** (recomendado por costo/riesgo) — descartado por
   el negocio: no quieren hardware local.
2. **Modo offline en el navegador**, acotado a las funciones mínimas para no
   perder ventas durante un corte, sin tocar dinero ni folios reales hasta
   reconectar. **Esta es la opción elegida.**

## Alcance

### Funciona sin conexión

- Ver el mapa de mesas con el último estado conocido (`mesas.mapa`).
- Abrir una mesa nueva y armar su comanda: agregar productos, cantidades,
  modificadores, usando el catálogo cacheado en el navegador.
- Agregar más productos a una comanda que ya estaba abierta antes del corte.
- "Pedir la cuenta" de una comanda con ítems sin sincronizar: calcula un
  **total preliminar** en el navegador (mismos precios cacheados) y lo marca
  como `Cuenta preliminar — sin folio, pendiente de conexión`. Hoy
  `SaleService::requestBill()` solo cambia el estado de la mesa a "por
  cobrar" (no toca folio ni dinero), así que este caso no necesita ninguna
  escritura real hasta que se sincronice.

### Bloqueado sin conexión (el botón se deshabilita con "Necesita conexión")

- Caja: apertura, movimientos, cierre (`CashRegisterService`).
- Cobrar / finalizar una venta (`SaleService::payOrder`) — es lo que asigna
  el folio de venta, descuenta inventario y dispara el ticket impreso.
- Cancelar ventas, vaciar mesas, y todo Administración/Reportes.

### Qué pasa al reconectar

Cada comanda armada offline (nueva o agregada a una mesa ya abierta) se
sincroniza en el orden en que se creó. Recién en ese momento:

- Si la mesa se abrió offline, se genera su `comanda_folio` real (secuencial,
  igual que hoy — `FolioGenerator`).
- Se disparan las comandas de cocina/barra (`PrintService::enqueueKitchenComandas`),
  igual que si el mesero las hubiera mandado en vivo.
- Si algo falla al sincronizar una comanda puntual (ver "Casos límite"), esa
  queda marcada para resolver a mano y no bloquea la sincronización de las
  demás.

## Arquitectura

No se duplica lógica de negocio. La comanda offline se arma con una capa
liviana en el navegador y, al reconectar, se "reproduce" contra el mismo
código que ya existe (`SaleService`) a través de un endpoint nuevo y chico.

```
┌─────────────────────────────┐        ┌──────────────────────────────┐
│  Navegador (mesas/comanda)   │        │  Servidor (Laravel)           │
│                              │        │                                │
│  1. Catálogo cacheado        │  GET   │  /mesas/catalogo-offline       │
│     (productos, precios,     │◄───────┤  (mientras hay conexión,       │
│      modificadores, mesas)   │        │   refresca el caché)           │
│                              │        │                                │
│  2. Detector de conexión     │  GET   │  /up (heartbeat liviano)        │
│     (online/offline + ping)  │◄───────┤                                │
│                              │        │                                │
│  3. Borrador offline por     │        │                                │
│     mesa (localStorage)      │        │                                │
│                              │        │                                │
│  4. Cola de sincronización   │  POST  │  /mesas/{mesa}/comanda/         │
│     (al reconectar, en orden)│───────►│  sincronizar                   │
│                              │        │  → SaleService::createDraftOrder│
│                              │        │  → SaleService::addItemsToOrder │
└─────────────────────────────┘        └──────────────────────────────┘
```

### 1. Caché del catálogo en el navegador

- Nuevo endpoint `GET /mesas/catalogo-offline` (mismo permiso que
  `ventas.crear`): devuelve JSON con productos activos + precio +
  modificadores + mesas y su estado actual. Se pide una vez al cargar
  `mesas.mapa`/`mesas.comanda` y se refresca solo (en segundo plano, sin
  bloquear la UI) cada pocos minutos mientras hay conexión.
- Se guarda en `localStorage` con un timestamp y se refresca cada 5 minutos
  mientras hay conexión (además de al cargar la página). Si el corte es tan
  largo que el caché queda viejo, se sigue usando igual (es mejor un precio
  desactualizado que no poder tomar el pedido), pero la UI offline muestra
  "Catálogo actualizado por última vez hace X" para que el mesero lo tenga en
  cuenta.

### 2. Detector de conexión

`navigator.onLine` no alcanza (indica si el dispositivo tiene una interfaz de
red activa, no si el servidor responde). Se usa, además, un heartbeat: un
`fetch` liviano a la ruta de salud que ya trae Laravel 12 (`/up`,
configurada en `bootstrap/app.php`) cada 15 segundos mientras la pantalla de
mesas/comanda está abierta. El estado "offline" real es:
`!navigator.onLine || heartbeat_fallido`. Al detectar que se recuperó,
dispara la sincronización automáticamente (además del botón manual).

### 3. Borrador offline por mesa

En `localStorage`, una entrada por mesa con ítems sin sincronizar:

```json
{
  "table_id": 7,
  "client_order_uuid": "uuid-v4-o-null-si-la-mesa-ya-tenia-orden-abierta",
  "existing_order_id": "id real si la mesa ya estaba abierta antes del corte",
  "people_count": 4,
  "items": [
    { "client_item_uuid": "uuid-v4", "product_id": 12, "quantity": 2, "modifiers": [...], "notes": null }
  ],
  "requested_bill": false,
  "created_at": "2026-09-05T20:10:00Z"
}
```

La UI de "armar comanda" offline reutiliza la misma pantalla de
`mesas.comanda` en un modo degradado: mismo catálogo, mismo carrito visual,
pero en vez de que cada click dispare una llamada a Livewire, acumula en este
borrador y muestra "Sin conexión — se va a mandar cuando vuelva internet. Sin
folio todavía."

### 4. Endpoint de sincronización (idempotente)

`POST /mesas/{table}/comanda/sincronizar` — autenticado por sesión normal
(no token de máquina, es un usuario logueado usando la app), permiso
`ventas.crear`.

Body: el borrador tal cual se guardó en `localStorage` (ver arriba).

Lógica:

1. **Resolver la orden:**
   - Si `existing_order_id` viene informado → debe ser la orden `Pending`
     actual de esa mesa. Si ya no lo es (alguien la cerró/canceló mientras
     tanto desde otro dispositivo) → error de conflicto, la comanda queda
     "requiere revisión manual" en el cliente, no se pierde el borrador.
   - Si no hay `existing_order_id` pero sí `client_order_uuid`:
     - Si ya existe una orden con ese `client_uuid` (columna nueva, única por
       negocio) → reintento de un sync previo que sí llegó a crear la orden
       pero la respuesta no volvió al cliente. Se reusa esa orden (idempotente).
     - Si no existe → primera vez que este borrador llega al servidor:
       `SaleService::createDraftOrder(...)` con `client_uuid` guardado.
       Acá se asigna el `comanda_folio` real.
2. **Agregar ítems:** de la lista del borrador, se descartan los que ya
   tengan un `order_items.client_uuid` existente en esa orden (ya
   sincronizados en un intento anterior) y se llama
   `SaleService::addItemsToOrder(...)` solo con los genuinamente nuevos. Esto
   dispara la impresión a cocina/barra como ya hace hoy.
3. Si `requested_bill` es `true`, se llama `SaleService::requestBill(...)`.
4. Responde con la orden actualizada (folio real incluido) para que el
   cliente actualice el mapa de mesas y borre el borrador local.

Este mismo endpoint sirve tanto para la sincronización automática al
reconectar como para un botón manual "Reintentar sincronización".

### 5. Cambios de esquema

- `orders`: columna `client_uuid` (string, nullable, único por
  `business_id`) — igual patrón que `folio`/`comanda_folio`.
- `order_items`: columna `client_uuid` (string, nullable, único por `order_id`).

Ambas nullable porque el flujo normal (mesero conectado) no las usa nunca;
solo existen para hacer idempotente la reproducción de un borrador offline.

### 6. UI

- Banner igual al de "sin conexión" del espejo, pero con mensaje operativo:
  "Sin conexión — podés seguir tomando pedidos, se van a enviar solos al
  reconectar."
- En el mapa de mesas, una mesa con borrador offline sin sincronizar muestra
  un indicador distinto (p. ej. punto naranja "pendiente de enviar") además
  de su estado normal.
- Caja y Cobrar: mismos botones de siempre, pero `disabled` + tooltip
  "Necesita conexión" cuando el detector marca offline.
- Pantalla de "comanda" en modo offline dice explícitamente que no hay folio
  todavía y que el pedido no se mandó a cocina/barra.

## Casos límite (documentados, no eliminados)

- **Dos dispositivos, misma mesa, offline al mismo tiempo:** se sincronizan
  en el orden en que llegan; puede quedar una mesa con comandas de dos
  orígenes. No se resuelve con más código — es una limitación real de operar
  sin servidor local. Se documenta para el personal.
- **Catálogo desactualizado:** el total preliminar de "pedir la cuenta"
  puede no coincidir con el total real al sincronizar si cambiaron precios
  durante el corte. El total real siempre lo recalcula el servidor
  (`recalculateTotals`), igual que hoy.
- **Se cierra la pestaña/se apaga el celular con un borrador sin
  sincronizar:** ese pedido se pierde, porque vive solo en el `localStorage`
  de ese dispositivo. Es una limitación aceptada explícitamente por el
  negocio al elegir no tener servidor local.
- **Cocina/barra no se enteran de nada mientras dura el corte** — consecuencia
  directa de "sin folio hasta reconectar"; no hay ticket que imprimir porque
  la orden todavía no existe en el servidor.
- **Conflicto al reconectar** (la mesa fue cerrada/cancelada desde otro lado
  mientras estaba offline): la comanda offline no se pierde, queda visible
  como "no se pudo sincronizar, revisar mesa X" para que el encargado decida
  a mano.

## Testing

- **Servidor (Pest/Feature):** cobertura completa del endpoint de
  sincronización — idempotencia por `client_uuid` en orden e ítems, folio
  asignado recién al sincronizar (no antes), conflicto cuando la orden ya no
  está `Pending`, permiso `ventas.crear` requerido, y que dispare
  `enqueueKitchenComandas` igual que el flujo en vivo.
- **Cliente (manual, con el navegador):** no hay test runner de JS en este
  repo. La capa de `localStorage`/detección de conexión se prueba a mano
  simulando offline (como se hizo en esta sesión con Playwright,
  `context().setOffline(true)`), documentando el procedimiento en el PR. Se
  mantiene la lógica de este módulo lo más simple posible (sin librerías
  nuevas) precisamente porque no hay tests automáticos que la respalden.

## Fuera de alcance de este cambio

- Cualquier tolerancia offline para Caja, Cobrar, Administración o Reportes.
- Resolver el caso de impresión a cocina/barra durante el corte (no hay
  forma de imprimir algo que el servidor todavía no vio).
- Failover de conectividad a nivel de infraestructura (router 4G de
  respaldo) — se lo ofrecimos al negocio y lo descartaron por ahora.
