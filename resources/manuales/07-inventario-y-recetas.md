# 07 · Inventario y recetas

Permite que el sistema **descuente insumos automáticamente** al vender y
lleve un **kardex** (historial de movimientos con saldo). Es opcional: puedes
operar el POS sin inventario y activarlo más adelante.

## Requisitos previos

- Manual 04 completo (productos).
- Política de inventario negativo elegida (manual 02).

## 1 · Insumos

**Inventario → Insumos** — requiere permiso *inventario.ajustar*.

1. **Nuevo insumo** por cada materia prima que quieras controlar:
   *Carne de res (kg)*, *Tortilla (pza)*, *Refresco lata (pza)*.
   - Unidad de medida y **existencia inicial** (lo que hay hoy en almacén).
   - Costo unitario si lo conoces (se actualiza solo al recibir compras).
2. Empieza por los insumos caros o críticos; no tienes que inventariar todo.

## 2 · Recetas

**Inventario → Recetas**

1. Elige un producto **inventariable** (marca esa casilla en el producto,
   manual 04).
2. Agrega cada insumo con la **cantidad** que consume **una unidad** del
   producto: *Hamburguesa = 0.150 kg carne + 1 pza pan + 2 pza jitomate*.
3. Al vender 3 hamburguesas, el sistema descuenta ×3.

## 3 · Movimientos manuales

**Inventario → Movimientos**

- **Entrada**: mermas recuperadas, ajuste de conteo al alza, traspaso.
- **Salida**: merma, robo, consumo interno, ajuste a la baja.
- Cada movimiento registra el **saldo resultante** en el kardex.

## 4 · Kardex

**Inventario → Kardex** — requiere permiso *inventario.ver_kardex*.

Muestra, por insumo, cada entrada/salida con fecha, motivo y saldo. Es la
fuente de verdad para auditar diferencias.

## Verificación

- Vende un producto inventariable → baja el stock de sus insumos según la
  receta (× cantidad vendida).
- **Cancela** esa venta → los insumos regresan al inventario.
- Con política **No permitir**, una venta que dejaría un insumo en negativo
  se bloquea.
- Un producto **no** inventariable no toca el stock.

Continúa con el **manual 08**.
