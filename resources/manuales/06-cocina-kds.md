# 06 · Cocina (KDS)

El **KDS** (Kitchen Display System) enruta cada producto vendido a la
**estación** que lo prepara y muestra el avance en pantalla. Si el negocio no
tiene cocina (ej. solo bebidas empaquetadas), puedes omitir este manual.

## Requisitos previos

- Manual 04 completo (productos creados).
- Si vas a imprimir comandas: manual 05 (terminales) hecho.

## 1 · Estaciones

**Administración → Estaciones**

1. **Nueva estación** por cada área de preparación: *Cocina*, *Barra*,
   *Parrilla*, *Postres*.
   - *Nombre* y *código*.
   - *Color*: para distinguirla de un vistazo en el tablero.
   - *Terminal impresora*: la terminal cuyo agente de impresión imprimirá las
     comandas de esta estación (opcional; manual 10). Si se deja vacío, la
     comanda solo se ve en pantalla.
2. Activa/desactiva estaciones sin borrarlas.

## 2 · Asignar productos a estaciones

Dos caminos:

- Al **crear/editar el producto** (*Catálogo → Productos*), campo *Estación
  de cocina*.
- Un producto **sin estación** no genera comanda: se cobra pero no aparece en
  el KDS ni se imprime en cocina (útil para botellas, cigarros, etc.).

## 3 · El tablero

**Operación → Cocina (KDS)** — requiere permiso *cocina.ver*.

- Cada comanda entra como **pendiente** y avanza:
  pendiente → en preparación → listo → entregado.
- El rol **Cocina** (y **Barra**) pueden mover los productos por el flujo.
- Cada cambio de estado queda con su hora, para medir tiempos.

## Verificación

- Vende en el POS un producto con estación → aparece en esa estación del KDS
  (y se encola su comanda impresa si la estación tiene terminal impresora).
- Vende un producto sin estación → **no** aparece en el KDS.
- Un usuario sin *cocina.ver* no puede abrir el tablero.

Continúa con el **manual 07**.
