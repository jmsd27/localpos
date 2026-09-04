#!/usr/bin/env node
/**
 * LOCALPOS - agente local de impresión.
 *
 * Corre como proceso aparte en la PC física conectada a la impresora térmica
 * ESC/POS (y opcionalmente al cajón de dinero, cableado a la impresora).
 * No requiere Internet: solo habla por LAN con este servidor Laravel, y con
 * la impresora habla por red (TCP :9100) o por USB/serie, según
 * `CONNECTION_TYPE`.
 *
 * Uso:
 *   node agent.js
 *
 * Configuración por variables de entorno (o edita los valores por defecto):
 *   LOCALPOS_URL            http://192.168.1.10:8000   (IP del servidor en la LAN)
 *   LOCALPOS_TERMINAL_TOKEN token del terminal (ver Admin > Terminales > Regenerar)
 *   POLL_INTERVAL_MS        intervalo de sondeo, por defecto 4000ms
 *   CONNECTION_TYPE         'red' (por defecto) o 'usb'
 *
 *   Modo red (CONNECTION_TYPE=red, o sin definir):
 *     PRINTER_HOST          IP de la impresora térmica en la red (puerto RAW 9100)
 *     PRINTER_PORT          puerto TCP, por defecto 9100
 *
 *   Modo USB (CONNECTION_TYPE=usb):
 *     USB_PATH              ruta del puerto serie de la impresora, p. ej. "COM3"
 *                           en Windows o "/dev/ttyUSB0" en Linux.
 *
 * El buffer ESC/POS que arma `buildReceipt` es idéntico sin importar el modo
 * de conexión; solo cambia el transporte usado para entregarlo a la
 * impresora (`sendToPrinter`), que ahora se ramifica según `CONNECTION_TYPE`
 * en vez de requerir que el usuario reescriba el código.
 *
 * IMPORTANTE para el modo USB: este script usa el paquete npm `serialport`
 * (ver package.json en esta misma carpeta). Antes de usar
 * CONNECTION_TYPE=usb en la PC física hay que correr `npm install` dentro de
 * scripts/print-agent/ — no viene pre-instalado porque no hay forma de
 * probar contra un puerto COM real desde el entorno donde se escribió este
 * código. También hay que identificar el puerto COM real de la impresora en
 * el Administrador de dispositivos de Windows (sección "Puertos (COM y
 * LPT)") y ponerlo en USB_PATH (p. ej. "COM3").
 */

const net = require('net');
const http = require('http');
const https = require('https');

const CONFIG = {
    baseUrl: process.env.LOCALPOS_URL || 'http://127.0.0.1:8000',
    terminalToken: process.env.LOCALPOS_TERMINAL_TOKEN || '',
    pollIntervalMs: Number(process.env.POLL_INTERVAL_MS || 4000),
    connectionType: (process.env.CONNECTION_TYPE || 'red').toLowerCase(),
    printerHost: process.env.PRINTER_HOST || '192.168.1.50',
    printerPort: Number(process.env.PRINTER_PORT || 9100),
    usbPath: process.env.USB_PATH || '',
};

// --- Construcción de comandos ESC/POS ---------------------------------

const ESC = 0x1b;
const GS = 0x1d;

function buildReceipt(content, openDrawer) {
    const chunks = [];

    chunks.push(Buffer.from([ESC, 0x40])); // ESC @ : inicializa la impresora
    chunks.push(Buffer.from(content, 'ascii'));
    chunks.push(Buffer.from('\n\n\n', 'ascii'));

    if (openDrawer) {
        // GS ~ p 0 : pulso al cajón de dinero conectado a la impresora (RJ11)
        chunks.push(Buffer.from([GS, 0x70, 0x00, 0x19, 0xfa]));
    }

    chunks.push(Buffer.from([GS, 0x56, 0x41, 0x10])); // GS V A : corte parcial de papel

    return Buffer.concat(chunks);
}

// --- Transporte hacia la impresora: red (TCP) o USB/serie --------------

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

function sendToPrinterViaUsb(buffer) {
    return new Promise((resolve, reject) => {
        if (!CONFIG.usbPath) {
            reject(new Error('Falta USB_PATH (p. ej. "COM3"). Revisa el Administrador de dispositivos de Windows.'));
            return;
        }

        // Import perezoso: `serialport` solo hace falta en modo USB (ver
        // package.json). Requiere `npm install` previo en la máquina física
        // — no viene incluida por defecto en este repo.
        let SerialPort;
        try {
            ({ SerialPort } = require('serialport'));
        } catch (error) {
            reject(new Error('No se encontró el paquete "serialport". Corre "npm install" en scripts/print-agent/ antes de usar CONNECTION_TYPE=usb.'));
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
                reject(new Error(`Error escribiendo al puerto ${CONFIG.usbPath}: ${error.message}`));
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

function sendToPrinter(buffer) {
    if (CONFIG.connectionType === 'usb') {
        return sendToPrinterViaUsb(buffer);
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

async function pollOnce() {
    const { jobs } = await request('GET', '/api/print-jobs');

    for (const job of jobs) {
        try {
            const buffer = buildReceipt(job.content || '', Boolean(job.open_drawer));
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

async function main() {
    if (!CONFIG.terminalToken) {
        console.error('Falta LOCALPOS_TERMINAL_TOKEN. Genera un token en Admin > Terminales.');
        process.exit(1);
    }

    if (CONFIG.connectionType === 'usb') {
        console.log(`Agente de impresión LOCALPOS iniciado. Servidor: ${CONFIG.baseUrl}, impresora: USB (${CONFIG.usbPath || 'sin configurar'})`);
    } else {
        console.log(`Agente de impresión LOCALPOS iniciado. Servidor: ${CONFIG.baseUrl}, impresora: ${CONFIG.printerHost}:${CONFIG.printerPort}`);
    }

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
