# 05 · Cajas y terminales

Sin esto **no se puede vender**. Una **terminal** es un punto de cobro
(una PC/tablet). Una **caja** es el fondo de dinero que se abre y cierra por
turno. Cada terminal trabaja contra una caja.

## Requisitos previos

- Manual 02 completo.

## 1 · Cajas

**Administración → Cajas**

1. **Nueva caja** por cada punto de dinero físico: *Caja Principal*,
   *Caja Barra*… Con nombre y código.
2. Normalmente basta **una caja** al arrancar.

## 2 · Terminales

**Administración → Terminales**

1. **Nueva terminal** por cada dispositivo que cobrará:
   - *Nombre* y *código* (*Caja 1*, *caja-1*).
   - *IP* (opcional) y *Nombre de impresora* (referencia).
   - **Caja asociada**: la caja del paso 1.
2. Al guardar, la terminal recibe un **token** de 48 caracteres
   (columna *Token*). Sirve para el **agente de impresión** (manual 10).
   - Botón **Regenerar** si el token se filtra o se pierde: el agente local
     debe actualizarse con el nuevo.

## 3 · Cómo se usa cada turno

1. En la terminal, el cajero entra a **Operación → Punto de venta**.
2. Si no hay terminal elegida, el sistema pide **seleccionar la terminal**
   (queda recordada en esa sesión/navegador).
3. **Operación → Caja → Apertura**: se cuenta el fondo inicial (efectivo con
   el que arranca) y se abre la sesión de caja.
4. Ya se puede cobrar. Durante el turno se registran **ingresos** y
   **retiros** desde *Caja → Movimientos*.
5. Al terminar: **Caja → Cierre**. Se cuenta el efectivo real y el sistema
   muestra la **diferencia** contra lo esperado.

## Verificación

- Entrar al POS sin terminal → redirige al selector de terminal.
- El **Mapa de mesas** exige terminal **y** caja abierta.
- Tras un cierre, *Historial de caja* muestra el turno con su diferencia.

## Errores comunes

| Síntoma | Solución |
|---|---|
| "No se puede abrir una terminal sin caja asociada" | Edita la terminal y asígnale una caja. |
| "La caja ya tiene una sesión abierta" | Alguien no cerró el turno anterior. Ciérralo en *Caja → Cierre*. |

Continúa con el **manual 06**.
