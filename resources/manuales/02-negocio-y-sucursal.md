# 02 · Negocio y sucursal

Completa los datos del negocio y define las políticas que afectan a todo el
sistema.

## Requisitos previos

- Manual 01 completo. Sesión iniciada como super admin o administrador.

## 1 · Datos del negocio

**Administración → Configuración**

1. Completa:
   - *Nombre* (comercial), *Razón social* y *RFC* — salen en tickets y
     reportes fiscales.
   - *Dirección*, *Teléfono*, *Correo*.
   - *Moneda* (3 letras) y *Zona horaria* — confirma que la zona horaria es
     la correcta antes de operar; cambiarla después mueve las fechas de
     cortes y reportes.
2. **Guarda**.

## 2 · Política de inventario negativo

En la misma pantalla, *Política de inventario negativo*:

| Opción | Qué hace |
|---|---|
| **No permitir** | Bloquea la venta si un insumo de la receta se quedaría en negativo. Úsala si el inventario está bien cargado. |
| **Permitir con alerta** (recomendado al arrancar) | Deja vender y registra el faltante; corriges después. |
| **Permitir** | Deja vender sin avisar. |

El cambio aplica **de inmediato** a las ventas siguientes.

## 3 · IVA / impuestos

LOCALPOS maneja el **impuesto por producto**, no un porcentaje global. Lo
defines al crear cada producto (*Tasa de impuesto*, manual 04). Si un
producto no lleva impuesto, deja la tasa en `0`.

## 4 · Sucursal

La instalación creó **Sucursal Principal** automáticamente. Una instalación
maneja **una sola sucursal**. Si el negocio abre otro local, se instala otro
LOCALPOS allá y ambos convergen en el espejo de la nube (manual 11); en ese
caso, asigna a cada sucursal un **código único** (`MTY-01`, `CDMX-02`, …).

## Verificación

- Vuelve a entrar a **Configuración**: los datos quedaron guardados.
- El nombre del negocio aparece en el encabezado / tickets.

Continúa con el **manual 03** (si hay servicio a mesa) o salta al **04**.
