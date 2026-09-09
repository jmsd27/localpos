# 7 · Impresión de comandas: cocina y barra

> Léelo cuando llegue el equipo (PC + impresoras) a la sucursal, antes de
> operar con clientes reales. Cubre: qué hacer al recibir el equipo, cómo
> configurarlo dentro de puntoYA, y una lista de pruebas paso a paso para
> confirmar que cada área imprime solo lo suyo antes de arrancar.

## Cómo funciona (resumen)

Cuando un mesero manda la comanda de una mesa, el sistema agrupa los
artículos por **estación de cocina** (`kitchen_station_id` de cada
producto) y genera **un ticket separado por estación** — no un ticket único
con todo. Cada estación tiene asignada una impresora (un `Terminal`), así
que el ticket de cada estación va únicamente a su propia impresora.

```
Mesero manda comanda de Mesa 3 (2 cervezas + 1 hamburguesa)
                    │
                    ▼
     PrintService::enqueueKitchenComandas()
       agrupa los artículos por estación
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
  Estación "Barra"        Estación "Cocina"
  (2 cervezas)             (1 hamburguesa)
        │                       │
        ▼                       ▼
  Terminal → impresora    Terminal → impresora
  física de BARRA          física de COCINA
```

Esto ya está construido y probado en el código (`app/Services/PrintService.php`,
método `enqueueKitchenComandas`). Los tickets de cocina/barra **no llevan
precio** — solo nombre del artículo, cantidad, modificadores y notas — así
que ni cocina ni barra ven el importe, solo caja.

Lo único que falta para que funcione con las impresoras reales es la
configuración de esta guía: crear un `Terminal` por impresora física y
vincular cada estación (Cocina / Barra) a su terminal.

## 1. Qué debe llegar

- La PC que va a quedarse prendida en el negocio corriendo el **agente de
  impresión** (no necesita ser potente; puede ser la misma PC donde corre
  puntoYA si el sistema se monta en local, o cualquier PC/mini-PC conectada
  a la misma red si el sistema está en la nube).
- Una impresora térmica por área (mínimo 2: Cocina y Barra). Ver
  `docs/Tareas/tarea- impresora para sistema - localPOS.txt` para las
  impresoras específicas ya definidas (HOSTECH HT-100 por USB/red, y
  térmicas Epson por USB serie).
- Cables de red / hub-switch si las impresoras van por Ethernet, o cable
  USB si van conectadas directo a la PC.

## 2. Instalación física

1. Conecta cada impresora a la corriente y ciérrale el rollo de papel.
2. Según cómo se conecte cada impresora, sigue una de estas tres rutas:

   **A. Impresora en red (WiFi o Ethernet, con IP)**
   - Conéctala al hub/switch o al WiFi del negocio.
   - Desde el menú de la propia impresora (o su hoja de configuración de
     red, según el manual del fabricante) imprime o revisa su **dirección
     IP**. Anótala — la vas a necesitar en el paso 3.
   - Idealmente configúrale una **IP fija** (o una reserva DHCP en el
     router) para que no cambie sola con el tiempo; si cambia, hay que
     volver a editar el Terminal en puntoYA.

   **B. Impresora HOSTECH HT-100 (u otra genérica) por USB**
   - Conéctala por USB a la PC del agente.
   - Windows la va a instalar como una impresora normal. Ve a
     **Configuración → Dispositivos → Impresoras y escáneres**, entra a
     sus propiedades y en la pestaña **Compartir** marca **"Compartir esta
     impresora"**. Anota el **nombre de recurso compartido** exacto (por
     ejemplo `POS-80`) — lo vas a necesitar en el paso 3.

   **C. Impresora Epson por USB serie**
   - Conéctala por USB a la PC del agente.
   - Abre el **Administrador de dispositivos** de Windows → sección
     **"Puertos (COM y LPT)"** → anota el puerto COM que le asignó (por
     ejemplo `COM3`).

## 3. Instalar y configurar el agente de impresión

El agente es un script de Node.js que ya viene en el proyecto
(`scripts/print-agent/agent.js`). Corre en la PC física conectada a las
impresoras y no necesita Internet: solo habla por LAN con puntoYA y con la
impresora.

1. En la PC del agente, instala [Node.js](https://nodejs.org/) (LTS) si no
   lo tiene.
2. Copia la carpeta `scripts/print-agent/` a esa PC (o clona/descarga el
   proyecto completo si el agente corre en el mismo equipo).
3. Si vas a usar el modo **USB por puerto serie** (impresoras Epson),
   entra a esa carpeta y corre `npm install` una sola vez (instala el
   paquete `serialport`). Para los otros dos modos (red, o USB instalada
   como impresora de Windows) no hace falta instalar nada.
4. **Antes de arrancar el agente necesitas crear el Terminal en puntoYA**
   (siguiente sección) porque el agente pide un **token** que se genera
   ahí.

## 4. Configurar en puntoYA: un Terminal por impresora

Entra con una cuenta con permisos de administrador (Arturo León, o el
super admin) a **Administración → Terminales** (`/admin/terminales`).

Por cada impresora física, dale **"Nueva terminal"** y llena:

| Campo | Qué poner |
|---|---|
| Nombre | Algo identificable, ej. `Impresora Cocina`, `Impresora Barra` |
| Código | Un slug único, ej. `imp-cocina`, `imp-barra` |
| Tipo de conexión | `Red (Ethernet/WiFi, por IP)` / `USB — impresora instalada en Windows` / `USB — puerto serie` según cómo la conectaste en el paso 2 |
| IP (si es Red) | La IP que anotaste en el paso 2A |
| Puerto de impresora (si es Red) | `9100` (valor de fábrica de casi todas las térmicas ESC/POS; cámbialo solo si el fabricante indica otro) |
| Nombre de impresora compartida (si es USB instalada en Windows) | El nombre de recurso compartido exacto del paso 2B, ej. `POS-80` |
| Puerto COM (si es USB por puerto serie) | El puerto del paso 2C, ej. `COM3` |
| Ancho de papel | `48` para rollo de 80mm (típico); `32` si el rollo es de 58mm |

Dale **Guardar**. En la tabla de terminales, junto al nuevo registro vas a
ver una columna **"Token del agente"** — ese es el valor que necesita el
agente de impresión para autenticarse. Haz clic para verlo completo (o usa
"Regenerar" si lo necesitas cambiar más adelante).

Repite esto una vez por cada impresora (mínimo dos: una para Cocina, otra
para Barra).

## 5. Vincular cada estación a su impresora

Ve a **Administración → Estaciones** (`/admin/estaciones`). Ya deberían
existir las estaciones **Cocina** y **Barra** (vienen del catálogo inicial
del negocio). Para cada una:

1. Dale **"Editar"**.
2. En **"Impresora de comandas"**, selecciona el Terminal que corresponde
   (el de Cocina → terminal de la impresora de cocina; el de Barra → el de
   la impresora de barra).
3. Guarda.

Esto es lo que hace que, al mandar una comanda, el ticket de cada estación
llegue a la impresora correcta.

> Si en algún momento agregan un tercer punto de impresión (por ejemplo,
> una segunda barra o una estación de postres), el mismo patrón aplica:
> crear su Terminal, crear o editar su Estación, vincularlos, y asignar esa
> estación a los productos correspondientes en **Administración →
> Productos**.

## 6. Arrancar el agente

En la PC física, con el token del paso 4 a la mano, arranca el agente
correspondiente a **cada** impresora (si una misma PC maneja las dos
impresoras, corre dos instancias del agente, una por terminal/token, en
dos ventanas de consola distintas).

Ejemplo para la impresora de **Cocina**, conectada por red en `192.168.1.51`:

```bash
cd scripts/print-agent
LOCALPOS_URL="http://<IP o dominio del servidor puntoYA>" \
LOCALPOS_TERMINAL_TOKEN="<token del Terminal de Cocina>" \
CONNECTION_TYPE=red \
PRINTER_HOST=192.168.1.51 \
PRINTER_PORT=9100 \
node agent.js
```

Ejemplo para la impresora HOSTECH de **Barra**, instalada como impresora
compartida de Windows con nombre `POS-80`:

```bash
cd scripts/print-agent
set LOCALPOS_URL=http://<IP o dominio del servidor puntoYA>
set LOCALPOS_TERMINAL_TOKEN=<token del Terminal de Barra>
set CONNECTION_TYPE=usb_impresora
set PRINTER_NAME=POS-80
node agent.js
```

Si todo está bien, la consola del agente se queda corriendo sin errores,
sondeando la cola cada pocos segundos. **Déjalo corriendo siempre** (para
que no se cierre al reiniciar la PC, conviene configurarlo como tarea
programada de Windows o servicio — pregúntame cuando lleguemos a ese punto
si quieres que te deje ese script también).

## 7. Pruebas paso a paso (antes de operar con clientes)

Sigue este orden. No pases al siguiente punto hasta que el anterior
funcione.

### Prueba 1 — El agente ve la cola

- [ ] Con el agente corriendo (paso 6) y **sin mandar ninguna comanda
      todavía**, ve a **Impresión → Cola de impresión** (`/impresion/cola`)
      dentro de puntoYA.
- [ ] Confirma que la pantalla carga sin error (aunque esté vacía).

### Prueba 2 — Apertura de turno

- [ ] Inicia sesión con un usuario Mesero (ej. Marco Guillén).
- [ ] Ve a **Mesas** y confirma que pide "terminal y caja abiertas" si
      aún no se abrió el turno — esto es esperado.
- [ ] Con un Administrador o Cajero, abre la caja del día
      (**Caja → Apertura**) y selecciona una terminal de POS/venta (no las
      de impresora que acabas de crear — esa es una terminal aparte para
      cobrar).

### Prueba 3 — Comanda dividida: un artículo de cada área

- [ ] Como Mesero, entra a una mesa de prueba (**Mesas** →
      selecciona una mesa libre).
- [ ] Agrega **un artículo de comida** (por ejemplo, "Hamburguesa
      Sencilla") y **un artículo de bebida** (por ejemplo, "Cerveza
      Corona" o cualquier cóctel/licor).
- [ ] Dale **"Enviar comanda"**.
- [ ] **Revisa físicamente las dos impresoras**:
  - [ ] La impresora de **Cocina** debe imprimir un ticket que diga
        únicamente "1 x Hamburguesa Sencilla" (sin la cerveza, sin
        precio).
  - [ ] La impresora de **Barra** debe imprimir un ticket aparte que diga
        únicamente "1 x Cerveza Corona" (sin la hamburguesa, sin precio).
- [ ] Si algún ticket no salió, no lo intentes de nuevo todavía — pasa a
      la Prueba 4 para diagnosticar antes de reintentar (reintentar sin
      saber la causa puede duplicar tickets cuando el problema se
      resuelva solo).

### Prueba 4 — Verificar la cola de impresión

- [ ] Ve a **Impresión → Cola de impresión** (`/impresion/cola`) con un
      Administrador.
- [ ] Deberías ver **dos** trabajos nuevos (uno por estación), cada uno
      con el nombre del Terminal correcto.
- [ ] Si un trabajo quedó en estado de error, revisa el mensaje de error
      mostrado ahí mismo — casi siempre es: agente no está corriendo, IP
      incorrecta, o impresora apagada/sin papel.
- [ ] Corrige la causa y usa el botón para **reintentar** ese trabajo
      específico (no repitas la comanda desde la mesa).

### Prueba 5 — Vista de cocina (KDS)

- [ ] Con un usuario con acceso a cocina, entra a **KDS**
      (`/kds`).
- [ ] Confirma que el pedido de la Prueba 3 aparece ahí, y que solo
      muestra los artículos de su propia estación (esto es independiente
      de la impresora — sirve como respaldo visual si una impresora
      falla).

### Prueba 6 — Cierre del ciclo (cobro)

- [ ] Desde la mesa de prueba, pide la cuenta y cóbrala
      (**Caja → Cobrar**).
- [ ] Confirma que el **ticket de venta** (el del cliente, con precios)
      sale por la impresora de caja — esta sí lleva precios; es un tipo de
      trabajo de impresión distinto (`TicketVenta`) al de las comandas de
      cocina/barra.
- [ ] Confirma que la mesa vuelve a quedar libre en el mapa de mesas.

### Prueba 7 — Comanda con varios artículos mezclados

- [ ] Repite la Prueba 3 pero con una mesa con **3 o más artículos
      mezclados** entre cocina y barra (por ejemplo: 2 cervezas, 1
      hamburguesa, 1 orden de papas, 1 coctel).
- [ ] Confirma que el ticket de Cocina agrupa **todos** los artículos de
      comida en un solo ticket (hamburguesa + papas), y el de Barra agrupa
      **todos** los de bebida (2 cervezas + coctel) — no debe salir un
      ticket por artículo, sino uno por estación.

### Prueba 8 — Falla controlada (opcional pero recomendada)

- [ ] Apaga o desconecta una de las dos impresoras a propósito.
- [ ] Manda una comanda con artículos de ambas áreas.
- [ ] Confirma que la impresora que sí está prendida **igual imprime su
      parte** (no se bloquea la comanda completa por la otra impresora).
- [ ] Confirma en **Cola de impresión** que el trabajo de la impresora
      apagada quedó marcado con error, no perdido.
- [ ] Prende la impresora de nuevo y usa **"Reintentar"** en ese trabajo —
      debe imprimir en ese momento.

## 8. Si algo no imprime — diagnóstico rápido

| Síntoma | Causa más probable | Qué revisar |
|---|---|---|
| Ninguna de las dos imprime nada, ni aparece en la Cola | El mesero mandó la comanda pero los productos no tienen estación asignada | **Administración → Productos**: confirma que cada producto tiene una "Estación de cocina" asignada |
| Aparece en la Cola pero se queda "Pendiente" para siempre | El agente no está corriendo, o no puede llegar al servidor | Revisa la consola del agente (¿tiene errores?); confirma `LOCALPOS_URL` y que la PC del agente tiene red hacia el servidor |
| Aparece con estado de **error** | El agente sí llegó a la cola pero no pudo hablarle a la impresora | Revisa que la impresora esté prendida, con papel, y que la IP/nombre compartido/puerto COM configurado en el Terminal sea el correcto |
| Imprime todo en una sola impresora, mezclado | La Estación no está vinculada a un Terminal distinto para cada área, o dos Terminales apuntan a la misma impresora por error | **Administración → Estaciones**: confirma que Cocina y Barra tienen Terminales *distintos* |
| El ticket de cocina/barra muestra precios | No debería pasar — el ticket de comanda (`ComandaCocina`) no incluye precio en el código. Si lo ves, es una señal de que algo se modificó; avísame para revisarlo | — |

---

Cuando termines las 8 pruebas con resultado correcto, el sistema queda
listo para operar con clientes reales. Guarda los tokens de cada Terminal
en un lugar seguro (no se muestran completos en la interfaz una vez
creados, solo se pueden regenerar).
