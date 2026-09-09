#!/usr/bin/env node
/**
 * LOCALPOS - agente local de impresión.
 *
 * Corre como proceso aparte en la PC física conectada a la impresora térmica
 * ESC/POS (y opcionalmente al cajón de dinero, cableado a la impresora).
 * No requiere Internet: solo habla por LAN con este servidor Laravel, y con
 * la impresora habla por red (TCP :9100) o por USB, en cualquiera de sus dos
 * formas, según `CONNECTION_TYPE`.
 *
 * Uso:
 *   node agent.js
 *
 * Lo mínimo que hay que definir por variable de entorno:
 *   LOCALPOS_URL            https://barlamartina.com.mx  (o la IP del servidor
 *                           en la LAN si es instalación local)
 *   LOCALPOS_TERMINAL_TOKEN token del terminal (Admin > Terminales > el token
 *                           de la fila BARRA o COCINA)
 *
 * TODO LO DEMÁS —IP y puerto de la impresora, tipo de conexión, ruta USB,
 * nombre compartido de Windows, ancho de papel, logo del ticket y líneas de
 * avance antes del corte— sale del terminal y de la configuración del negocio
 * (Administración → Terminales / Configuración → Ticket de venta) y llega solo
 * en cada sondeo a /api/print-jobs. No hace falta repetirlo acá. Las variables
 * de abajo siguen existiendo como override manual: si definís una, esa gana
 * sobre lo que diga el servidor.
 *
 *   POLL_INTERVAL_MS        intervalo de sondeo, por defecto 4000ms
 *   CONNECTION_TYPE         'red' (por defecto), 'usb_serial' o 'usb_impresora'
 *   TICKET_FEED_LINES       líneas en blanco que se avanzan antes del corte
 *                           (por defecto 3, o lo que diga la configuración del
 *                           negocio). El logo del ticket no se puede forzar por
 *                           env: se sube en el panel y llega en el sondeo.
 *
 *   Modo red (CONNECTION_TYPE=red, o sin definir):
 *     PRINTER_HOST          IP de la impresora térmica en la red (puerto RAW 9100)
 *     PRINTER_PORT          puerto TCP, por defecto 9100
 *
 *   Modo USB por puerto serie (CONNECTION_TYPE=usb_serial):
 *     Para impresoras que Windows reconoce como un puerto COM virtual al
 *     conectarlas (típico en algunas Epson).
 *     USB_PATH              ruta del puerto serie, p. ej. "COM3" en Windows
 *                           o "/dev/ttyUSB0" en Linux. Identifícalo en el
 *                           Administrador de dispositivos de Windows, sección
 *                           "Puertos (COM y LPT)".
 *     Requiere el paquete npm `serialport` (ver package.json en esta misma
 *     carpeta) — corre `npm install` en scripts/print-agent/ antes de usar
 *     este modo.
 *
 *   Modo USB como impresora instalada en Windows (CONNECTION_TYPE=usb_impresora):
 *     Para impresoras que, al conectarlas por USB, Windows instala como una
 *     impresora normal (Panel de control > Dispositivos e impresoras) en vez
 *     de un puerto COM — el caso típico de impresoras térmicas POS genéricas
 *     como la HOSTECH HT-100. No requiere ningún paquete npm ni puerto COM:
 *     se manda el ticket en crudo (RAW) al spooler de Windows con el comando
 *     `copy /b`, aprovechando que Windows deja escribir bytes crudos a una
 *     impresora si se referencia como recurso compartido de red (aunque sea
 *     de la propia PC).
 *     PRINTER_NAME          nombre EXACTO con el que se compartió la
 *                           impresora en Windows (clic derecho sobre la
 *                           impresora en "Dispositivos e impresoras" >
 *                           Propiedades de impresora > pestaña Compartir >
 *                           marcar "Compartir esta impresora" > anota el
 *                           "Nombre de recurso compartido", p. ej. "POS-80").
 *                           Este es un paso de configuración de Windows que
 *                           hay que hacer una sola vez en cada PC, antes de
 *                           usar este modo.
 *     Solo funciona en Windows (`process.platform === 'win32'`); en
 *     cualquier otro sistema operativo este modo falla con un error claro.
 *
 * El buffer ESC/POS que arma `buildReceipt` es idéntico sin importar el modo
 * de conexión; solo cambia el transporte usado para entregarlo a la
 * impresora (`sendToPrinter`), que se ramifica según `CONNECTION_TYPE` en
 * vez de requerir que el usuario reescriba el código.
 *
 * Nota de compatibilidad: si venías usando CONNECTION_TYPE=usb (nombre
 * anterior a que existieran los dos modos), este agente lo sigue aceptando
 * como alias de 'usb_serial' — conviene actualizar tu configuración al
 * nombre nuevo de todos modos.
 */

const net = require('net');
const http = require('http');
const https = require('https');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { execFile } = require('child_process');

function normalizeConnectionType(raw) {
    const value = (raw || 'red').toLowerCase();

    // Alias de compatibilidad: antes de que existieran dos modos USB, el
    // único valor era 'usb'. Se trata como el actual 'usb_serial'.
    if (value === 'usb') return 'usb_serial';

    return value;
}

// Lo único imprescindible por env: dónde está el servidor y el token del
// terminal. El resto (IP/puerto de la impresora, tipo de conexión, ruta USB,
// nombre compartido, ancho de papel) sale del terminal configurado en
// Administración → Terminales y llega en cada respuesta de /api/print-jobs.
// Si igual definís una de esas variables de entorno, esa gana sobre lo que
// diga el servidor (útil para forzar algo puntual sin tocar la config).
const CONFIG = {
    baseUrl: process.env.LOCALPOS_URL || 'http://127.0.0.1:8000',
    terminalToken: process.env.LOCALPOS_TERMINAL_TOKEN || '',
    pollIntervalMs: Number(process.env.POLL_INTERVAL_MS || 4000),
    connectionType: process.env.CONNECTION_TYPE ? normalizeConnectionType(process.env.CONNECTION_TYPE) : 'red',
    printerHost: process.env.PRINTER_HOST || '',
    printerPort: process.env.PRINTER_PORT ? Number(process.env.PRINTER_PORT) : 9100,
    usbPath: process.env.USB_PATH || '',
    printerName: process.env.PRINTER_NAME || '',
    ticketFeedLines: process.env.TICKET_FEED_LINES ? Number(process.env.TICKET_FEED_LINES) : 3,
    // Búfer ESC/POS (base64) del logo del ticket de venta. No se puede fijar
    // por env; llega del servidor cuando hay trabajos pendientes.
    ticketLogoBase64: '',
};

// Qué valores fijó el operador por env: esos no se pisan con la config del
// servidor.
const ENV_LOCKED = {
    connectionType: Boolean(process.env.CONNECTION_TYPE),
    printerHost: Boolean(process.env.PRINTER_HOST),
    printerPort: Boolean(process.env.PRINTER_PORT),
    usbPath: Boolean(process.env.USB_PATH),
    printerName: Boolean(process.env.PRINTER_NAME),
    ticketFeedLines: Boolean(process.env.TICKET_FEED_LINES),
};

let lastTargetDescription = '';

/**
 * Aplica la configuración de impresora que mandó el servidor (el terminal
 * cargado en Admin → Terminales), respetando cualquier override por env.
 */
function applyServerTerminalConfig(terminal) {
    if (!terminal || typeof terminal !== 'object') return;

    if (!ENV_LOCKED.connectionType && terminal.connection_type) {
        CONFIG.connectionType = normalizeConnectionType(terminal.connection_type);
    }
    if (!ENV_LOCKED.printerHost && terminal.ip_address) {
        CONFIG.printerHost = terminal.ip_address;
    }
    if (!ENV_LOCKED.printerPort && terminal.printer_port) {
        CONFIG.printerPort = Number(terminal.printer_port);
    }
    if (!ENV_LOCKED.usbPath && terminal.usb_path) {
        CONFIG.usbPath = terminal.usb_path;
    }
    if (!ENV_LOCKED.printerName && terminal.printer_name) {
        CONFIG.printerName = terminal.printer_name;
    }

    const target = describeTarget();
    if (target !== lastTargetDescription) {
        console.log(`[${new Date().toISOString()}] Config de impresora (terminal "${terminal.name || '?'}"): ${target}`);
        lastTargetDescription = target;
    }
}

/**
 * Aplica la configuración del ticket de venta que mandó el servidor
 * (Administración → Configuración → Ticket de venta): líneas de avance antes
 * del corte y el logo ya convertido a ESC/POS. El logo solo llega cuando hay
 * trabajos pendientes; si no vino, se limpia.
 */
function applyServerTicketConfig(ticket) {
    if (!ticket || typeof ticket !== 'object') return;

    if (!ENV_LOCKED.ticketFeedLines && Number.isFinite(Number(ticket.feed_lines))) {
        CONFIG.ticketFeedLines = Math.max(0, Math.min(20, Math.trunc(Number(ticket.feed_lines))));
    }

    CONFIG.ticketLogoBase64 = typeof ticket.logo === 'string' ? ticket.logo : '';
}

// --- Construcción de comandos ESC/POS ---------------------------------

const ESC = 0x1b;
const GS = 0x1d;

function buildReceipt(content, openDrawer, options = {}) {
    const chunks = [];

    chunks.push(Buffer.from([ESC, 0x40])); // ESC @ : inicializa la impresora

    if (options.logoBase64) {
        // El servidor ya mandó el logo como comando GS v 0 (mapa de bits).
        chunks.push(Buffer.from([ESC, 0x61, 0x01])); // ESC a 1 : centrar
        chunks.push(Buffer.from(options.logoBase64, 'base64'));
        chunks.push(Buffer.from([ESC, 0x61, 0x00])); // ESC a 0 : volver a la izquierda
        chunks.push(Buffer.from('\n', 'ascii'));
    }

    chunks.push(Buffer.from(content, 'ascii'));

    const feedLines = Number.isFinite(options.feedLines) ? Math.max(0, Math.trunc(options.feedLines)) : 3;
    chunks.push(Buffer.from('\n'.repeat(feedLines), 'ascii'));

    if (openDrawer) {
        // GS ~ p 0 : pulso al cajón de dinero conectado a la impresora (RJ11)
        chunks.push(Buffer.from([GS, 0x70, 0x00, 0x19, 0xfa]));
    }

    chunks.push(Buffer.from([GS, 0x56, 0x41, 0x10])); // GS V A : corte parcial de papel

    return Buffer.concat(chunks);
}

// --- Transporte hacia la impresora: red (TCP), USB/serie o USB como ------
// --- impresora instalada en Windows ---------------------------------------

function sendToPrinterViaNetwork(buffer) {
    return new Promise((resolve, reject) => {
        const socket = net.createConnection({ host: CONFIG.printerHost, port: CONFIG.printerPort }, () => {
            socket.write(buffer, () => socket.end());
        });

        socket.setTimeout(5000);
        socket.on('timeout', () => { socket.destroy(); reject(new Error('Tiempo de espera agotado al conectar con la impresora')); });
        socket.on('error', reject);
        socket.on('close', (hadError) => { if (!hadError) resolve(); });
    });
}

function sendToPrinterViaUsbSerial(buffer) {
    return new Promise((resolve, reject) => {
        if (!CONFIG.usbPath) {
            reject(new Error('Falta USB_PATH (p. ej. "COM3"). Revisa el Administrador de dispositivos de Windows.'));
            return;
        }

        // Import perezoso: `serialport` solo hace falta en modo usb_serial
        // (ver package.json). Requiere `npm install` previo en la máquina
        // física — no viene incluida por defecto en este repo.
        let SerialPort;
        try {
            ({ SerialPort } = require('serialport'));
        } catch (error) {
            reject(new Error('No se encontró el paquete "serialport". Corre "npm install" en scripts/print-agent/ antes de usar CONNECTION_TYPE=usb_serial.'));
            return;
        }

        const port = new SerialPort({ path: CONFIG.usbPath, baudRate: 9600 }, (error) => {
            if (error) {
                reject(new Error(`No se pudo abrir el puerto ${CONFIG.usbPath}: ${error.message}`));
            }
        });

        port.on('error', reject);

        port.write(buffer, (error) => {
            if (error) {
                port.close(() => reject(new Error(`Error escribiendo al puerto ${CONFIG.usbPath}: ${error.message}`)));
                return;
            }

            port.drain((drainError) => {
                port.close(() => {
                    if (drainError) {
                        reject(new Error(`Error al vaciar el buffer del puerto ${CONFIG.usbPath}: ${drainError.message}`));
                    } else {
                        resolve();
                    }
                });
            });
        });
    });
}

/**
 * Manda el buffer ESC/POS a una impresora instalada/compartida en Windows,
 * usando el truco clásico de `copy /b` contra el recurso compartido de la
 * propia PC (\\localhost\<nombre>) — Windows entrega esos bytes tal cual al
 * spooler en modo RAW, sin pasar por ningún driver que los reinterprete.
 * No requiere ningún paquete npm adicional (solo módulos nativos de Node).
 */
function sendToPrinterViaWindowsPrinter(buffer) {
    return new Promise((resolve, reject) => {
        if (process.platform !== 'win32') {
            reject(new Error('CONNECTION_TYPE=usb_impresora solo funciona en Windows.'));
            return;
        }

        if (!CONFIG.printerName) {
            reject(new Error('Falta PRINTER_NAME (el nombre con el que compartiste la impresora en Windows, p. ej. "POS-80").'));
            return;
        }

        const tempFile = path.join(os.tmpdir(), `localpos-print-${Date.now()}-${crypto.randomBytes(4).toString('hex')}.prn`);
        const uncPath = `\\\\localhost\\${CONFIG.printerName}`;

        fs.writeFile(tempFile, buffer, (writeError) => {
            if (writeError) {
                reject(new Error(`No se pudo escribir el archivo temporal para imprimir: ${writeError.message}`));
                return;
            }

            execFile('cmd.exe', ['/c', 'copy', '/b', tempFile, uncPath], (execError, _stdout, stderr) => {
                fs.unlink(tempFile, () => {}); // limpieza best-effort, no bloquea el resultado

                if (execError) {
                    reject(new Error(
                        `No se pudo imprimir en "${CONFIG.printerName}" (¿está compartida en Windows con ese nombre exacto? `
                        + `¿está prendida y con papel?): ${stderr || execError.message}`
                    ));
                    return;
                }

                resolve();
            });
        });
    });
}

function sendToPrinter(buffer) {
    if (CONFIG.connectionType === 'usb_serial') {
        return sendToPrinterViaUsbSerial(buffer);
    }

    if (CONFIG.connectionType === 'usb_impresora') {
        return sendToPrinterViaWindowsPrinter(buffer);
    }

    return sendToPrinterViaNetwork(buffer);
}

// --- Cliente HTTP contra el servidor LOCALPOS --------------------------

function request(method, path, body) {
    return new Promise((resolve, reject) => {
        const url = new URL(path, CONFIG.baseUrl);
        const transport = url.protocol === 'https:' ? https : http;
        const payload = body ? JSON.stringify(body) : null;

        const req = transport.request(url, {
            method,
            headers: {
                'X-Terminal-Token': CONFIG.terminalToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(payload ? { 'Content-Length': Buffer.byteLength(payload) } : {}),
            },
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    resolve(data ? JSON.parse(data) : null);
                } else {
                    reject(new Error(`HTTP ${res.statusCode}: ${data}`));
                }
            });
        });

        req.on('error', reject);
        if (payload) req.write(payload);
        req.end();
    });
}

function printerTargetMissing() {
    if (CONFIG.connectionType === 'usb_serial') return !CONFIG.usbPath;
    if (CONFIG.connectionType === 'usb_impresora') return !CONFIG.printerName;

    return !CONFIG.printerHost;
}

async function pollOnce() {
    const { jobs, terminal, ticket } = await request('GET', '/api/print-jobs');

    applyServerTerminalConfig(terminal);
    applyServerTicketConfig(ticket);

    if (jobs.length > 0 && printerTargetMissing()) {
        console.error(
            `[${new Date().toISOString()}] Hay ${jobs.length} trabajo(s) en cola pero este terminal no tiene `
            + `impresora configurada (Administración → Terminales → Editar → IP / tipo de conexión). `
            + `Quedan pendientes y se imprimen apenas se configure.`
        );
        return;
    }

    for (const job of jobs) {
        try {
            const buffer = buildReceipt(job.content || '', Boolean(job.open_drawer), {
                logoBase64: job.type === 'ticket_venta' ? CONFIG.ticketLogoBase64 : '',
                feedLines: CONFIG.ticketFeedLines,
            });
            await sendToPrinter(buffer);
            await request('POST', `/api/print-jobs/${job.id}/ack`);
            console.log(`[${new Date().toISOString()}] Trabajo ${job.id} (${job.type}) impreso.`);
        } catch (error) {
            console.error(`[${new Date().toISOString()}] Error imprimiendo trabajo ${job.id}:`, error.message);
            try {
                await request('POST', `/api/print-jobs/${job.id}/fail`, { error: error.message.slice(0, 500) });
            } catch (ackError) {
                console.error('No se pudo reportar el error al servidor:', ackError.message);
            }
        }
    }
}

function describeTarget() {
    if (CONFIG.connectionType === 'usb_serial') {
        return `USB puerto serie (${CONFIG.usbPath || 'sin configurar'})`;
    }

    if (CONFIG.connectionType === 'usb_impresora') {
        return `USB impresora instalada en Windows (${CONFIG.printerName || 'sin configurar'})`;
    }

    return CONFIG.printerHost ? `${CONFIG.printerHost}:${CONFIG.printerPort}` : 'red (IP sin configurar todavía)';
}

async function main() {
    if (!CONFIG.terminalToken) {
        console.error('Falta LOCALPOS_TERMINAL_TOKEN. Genera un token en Admin > Terminales.');
        process.exit(1);
    }

    console.log(
        `Agente de impresión LOCALPOS iniciado. Servidor: ${CONFIG.baseUrl}. `
        + `La impresora se toma del terminal configurado en el sistema (llega en el primer sondeo).`
    );

    // eslint-disable-next-line no-constant-condition
    while (true) {
        try {
            await pollOnce();
        } catch (error) {
            console.error(`[${new Date().toISOString()}] Error consultando la cola:`, error.message);
        }

        await new Promise((resolve) => setTimeout(resolve, CONFIG.pollIntervalMs));
    }
}

main();
