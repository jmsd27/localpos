# 04 · Catálogo

El corazón del POS: qué se vende, a qué precio y con qué opciones. Hazlo en
este orden: **categorías → modificadores → productos**.

## Requisitos previos

- Manual 02 completo. Ten la carta a la mano.

## 1 · Categorías

**Catálogo → Categorías**

1. **Nueva categoría** por cada grupo de la carta: *Entradas*, *Platos
   fuertes*, *Bebidas*, *Postres*, *Cervezas*…
2. El orden de las categorías es el que verá el cajero en el POS.

## 2 · Modificadores

**Catálogo → Modificadores**

Un **grupo de modificadores** es una pregunta; sus **opciones** son las
respuestas.

1. **Nuevo grupo**: nombre (*Término de la carne*, *Extras*, *Tamaño*).
2. Define el **mínimo** y **máximo** de opciones que se pueden elegir:
   - Término: min 1, max 1 (obligatorio, una sola).
   - Extras: min 0, max 5 (opcional, varias).
   - El máximo no puede ser menor que el mínimo.
3. Agrega las **opciones** con su **precio adicional** (0 si no cuesta más):
   *Bien cocida (+$0)*, *Queso extra (+$25)*.

## 3 · Productos

**Catálogo → Productos**

1. **Nuevo producto**:
   - *Nombre*, *Categoría*, *Precio* (obligatorio).
   - *Tasa de impuesto*: el IVA de ese producto (`0` si no aplica).
   - *Estación de cocina*: dónde se prepara (se configura en el manual 06;
     puedes dejarlo vacío ahora y volver).
   - *Inventariable*: actívalo solo si vas a descontar insumos por receta
     (manual 07).
2. Asocia los **grupos de modificadores** que apliquen a ese producto.
3. Repite para toda la carta. Desactiva productos agotados en vez de
   borrarlos (conserva el histórico de ventas).

## 4 · Menú QR público (opcional)

**Catálogo → Menús QR**

1. Selecciona los productos/categorías que quieres mostrar al público.
2. Comparte la URL `/menu` (o su QR) en mesas y redes. Es **solo lectura**,
   sin precios de costo ni datos internos.

## Verificación

- En **Punto de venta** aparecen las categorías y sus productos.
- Un producto con un modificador **obligatorio** (min ≥ 1) no se agrega al
  ticket hasta elegir una opción.
- El total del ticket suma bien precio + modificadores + impuesto.

Continúa con el **manual 05**.
