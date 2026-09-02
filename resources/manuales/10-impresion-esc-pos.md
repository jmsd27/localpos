# 10 · Impresión ESC/POS

LOCALPOS no imprime directo: encola los trabajos (tickets y comandas) y un
**agente local** en Node los envía a la impresora térmica. Así el servidor no
necesita drivers ni estar junto a la impresora.

## Requisitos previos

- Manual 05 (terminales con su token).
- Manual 06 si vas a imprimir comandas de cocina.
- **Node.js** instalado en la PC conectada a la impresora.
- Impresora térmica **ESC/POS**. El agente asume una impresora de **red**
  (Ethernet/WiFi) escuchando en el puerto **9100**. Para impresora **USB** hay
  que adaptar la función `sendToPrinter` del agente (ver comentario en
  `scripts/print-agent/agent.js`).

## 1 · Obtener el token de la terminal

**Administración → Terminales** → columna *Token*. Si necesitas uno nuevo,
botón **Regenerar** (y actualiza el agente).

## 2 · Configurar y correr el agente

En la PC de la impresora, dentro de `scripts/print-agent/`:

```bash
set LOCALPOS_URL=http://192.168.1.10:8000      REM IP del servidor en la LAN
set LOCALPOS_TERMINAL_TOKEN=<token de la terminal>
set PRINTER_HOST=192.168.1.50                  REM IP de la impresora (puerto 9100)
node agent.js
```

El agente sondea cada 4 s los trabajos **pendientes de su terminal**, los
imprime y los confirma. Si falla, reporta el error y el trabajo queda para
reintentar.

Para que arranque solo con Windows: crea un `.bat` con esas líneas y
regístralo como **Tarea Programada** "al iniciar sesión".

## 3 · Ruteo

| Trabajo | A qué terminal se encola |
|---|---|
| **Ticket de venta** | La terminal donde se cobró. |
| **Comanda de cocina** | La *terminal impresora* configurada en la **estación** del producto (manual 06). |
| **Apertura de cajón** | La terminal de cobro (pulso RJ11 vía la impresora). |

El cajón se abre automáticamente al cobrar **en efectivo**; con solo tarjeta,
no. También hay apertura manual desde el POS.

## 4 · Monitoreo

**Administración → Cola de impresión** — requiere *configuracion.editar*.

- Ver trabajos pendientes, impresos y con error.
- **Reintentar** un trabajo fallido.
- Encolar una **apertura de cajón** manual.

## Verificación

- Con el agente corriendo, cobra una venta → el ticket sale en segundos y el
  trabajo pasa a *impreso* en la cola.
- Vende un producto con estación que tiene terminal impresora → sale la
  comanda en esa impresora.
- Sin token / token inválido, el agente recibe **401** y no imprime nada.
- Una terminal no puede confirmar los trabajos de otra.

Continúa con el **manual 11**.
