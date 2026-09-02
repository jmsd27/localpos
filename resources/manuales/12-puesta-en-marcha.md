# 12 · Puesta en marcha

Checklist final antes de abrir al público. Recórrelo en el equipo real, con
el personal presente.

## Datos y catálogo

- [ ] Datos del negocio completos (razón social, RFC, dirección) — *Configuración*.
- [ ] Zona horaria correcta.
- [ ] Política de inventario negativo definida.
- [ ] Todas las categorías creadas y en el orden deseado.
- [ ] Todos los productos con **precio** y **tasa de impuesto** correctos.
- [ ] Modificadores con sus mínimos/máximos y precios.
- [ ] Productos asignados a su **estación** (o dejados sin estación a propósito).

## Servicio a mesa (si aplica)

- [ ] Salones y mesas creados y coinciden con el local físico.
- [ ] El *Mapa de mesas* se ve completo y todas las mesas "libres".

## Cobro

- [ ] Una caja creada.
- [ ] Una terminal por cada punto de cobro, con su caja asignada.
- [ ] En cada terminal: **seleccionar terminal** hecho y **caja abierta** con
      su fondo inicial.
- [ ] Venta de prueba de principio a fin: agregar productos → cobrar
      efectivo → cambio correcto → ticket impreso → cajón abre.
- [ ] Venta de prueba con tarjeta: **no** abre el cajón.
- [ ] Anular la venta de prueba y verificar que el efectivo esperado vuelve a
      su lugar.

## Cocina

- [ ] Estaciones creadas con su color y (si imprimen) su terminal impresora.
- [ ] La venta de prueba apareció en el KDS en la estación correcta.
- [ ] El rol Cocina puede avanzar los productos por el flujo.

## Impresión

- [ ] Agente de impresión corriendo en la PC de cada impresora y registrado
      para arrancar con Windows.
- [ ] *Cola de impresión* sin trabajos en error.

## Inventario (si se usa)

- [ ] Insumos con existencia inicial cargada.
- [ ] Recetas de los productos inventariables.
- [ ] La venta de prueba descontó los insumos correctos; la anulación los
      devolvió.

## Personal

- [ ] Un usuario por persona, con su rol correcto.
- [ ] Contraseña del super admin cambiada por una fuerte y guardada en lugar
      seguro.
- [ ] Cuentas de personas que ya no están → **desactivadas**.

## Respaldos

- [ ] El respaldo automático diario (03:00) está programado — verifícalo tras
      el primer día (*Administración → Respaldos*).
- [ ] Sabes cómo **descargar** un respaldo y dónde se guardan.

## Nube (si se implementó)

- [ ] `sync:push` deja `Pendientes: 0`.
- [ ] La venta de prueba se ve en el espejo.
- [ ] Scheduler de Windows activo (`schtasks /query /tn LocalposScheduler`).

## Listo

Cuando todo lo anterior esté marcado, el negocio puede abrir. Deja este
manual y el 05 (apertura/cierre de caja) a mano para el personal las primeras
semanas.
