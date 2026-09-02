# 00 · Antes de empezar

Esta guía lleva un negocio nuevo desde cero hasta poder vender. Sigue los
manuales **en orden**: cada uno asume que los anteriores ya están hechos.

## Cómo funciona LOCALPOS

- **Todo es local.** Un LOCALPOS (Laravel + MySQL) por sucursal física,
  servido en la red local. **No necesita internet para vender.**
- El **espejo en la nube** (opcional, manual 11) es solo para *ver* reportes
  en remoto. Nunca se opera contra él.
- Una instalación = **un negocio**. Si abres otra sucursal, es otra
  instalación de LOCALPOS con su propia base de datos.

## Requisitos de la máquina (una vez)

- Laragon con **PHP 8.4** y **MySQL 8** corriendo.
- El proyecto en `C:\laragon\www\localpos`.
- Dependencias instaladas: `composer install` y `npm install && npm run build`
  ya ejecutados.
- La base de datos `localpos` creada y vacía.
- `.env` copiado de `.env.example` con `APP_KEY` generada
  (`php artisan key:generate`).

> Verifica que todo está sano antes de seguir:
> `php artisan test` debe terminar en verde.

## Qué reunir antes de configurar

Ten a mano, en papel o en una hoja de cálculo:

1. **Datos del negocio**: nombre comercial, razón social / RFC, dirección,
   teléfono, si cobras IVA y a qué tasa.
2. **Mapa del local** (si hay servicio a mesa): salones/áreas y cuántas mesas
   por salón.
3. **La carta**: categorías, productos con precio, y qué lleva cada
   modificador (ej. término de la carne, extras).
4. **Estaciones de preparación**: cocina, barra, parrilla… y qué productos
   salen en cada una.
5. **Insumos a inventariar** y la **receta** de los platos que sí descuentan
   inventario.
6. **Personal**: nombre y rol de cada quien (cajero, mesero, cocina…).
7. **Impresoras**: modelo, cómo se conectan (USB/red) y en qué estación va
   cada una.
8. **Cajas registradoras**: cuántas y con qué terminal (punto de cobro)
   trabaja cada una.

## Orden recomendado

| # | Manual | Para qué |
|---|--------|----------|
| 01 | Instalación y primer acceso | Crear el negocio y el primer usuario |
| 02 | Negocio y sucursal | Datos fiscales, IVA, políticas |
| 03 | Salones y mesas | Solo si hay servicio a mesa |
| 04 | Catálogo | Categorías, modificadores, productos, menú QR |
| 05 | Cajas y terminales | Puntos de cobro y sus tokens |
| 06 | Cocina (KDS) | Estaciones y ruteo de comandas |
| 07 | Inventario y recetas | Insumos, recetas, kardex |
| 08 | Compras y proveedores | Reabastecer insumos |
| 09 | Usuarios y roles | Altas de personal |
| 10 | Impresión ESC/POS | Agente de impresión y tickets |
| 11 | Espejo en la nube | Reportes remotos (opcional) |
| 12 | Puesta en marcha | Checklist final antes de abrir |
