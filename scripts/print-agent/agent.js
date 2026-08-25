#!/usr/bin/env node
/**
 * LOCALPOS - agente local de impresión.
 *
 * Corre como proceso aparte en la PC física conectada a la impresora térmica
 * ESC/POS (y opcionalmente al cajón de dinero, cableado a la impresora).
 * No requiere Internet: solo habla por LAN con este servidor Laravel y por
 * red/USB con la impresora.
 *
 * Uso:
 *   node agent.js
 *
 * Configuración por variables de entorno (o edita los valores por defecto):
 *   LOCALPOS_URL            http://192.168.1.10:8000   (IP del servidor en la LAN)
 *   LOCALPOS_TERMINAL_TOKEN token del terminal (ver Admin > Terminales > Regenerar)
 *   PRINTER_HOST            IP de la impresora térmica en la red (modo LAN, puerto 9100)
 *   POLL_INTERVAL_MS        intervalo de sondeo, por defecto 4000ms
 *
 * Este script asume una impresora térmica de red (Ethernet/WiFi) escuchando
 * en el puerto RAW 9100, el estándar de facto para impresoras ESC/POS de
 * cocina/mostrador. Para impresoras USB locales, sustituye `sendToPrinter`
 * por la llamada al driver/librería correspondiente (p. ej. node-thermal-printer
 * o escpos con el adaptador USB) manteniendo el mismo contrato: recibe un
 * Buffer de bytes ESC/POS y lo entrega a la impresora.
 */

const net = require('net');
const http = require('http');
const https = require('https');

const CONFIG = {
    baseUrl: process.env.LOCALPOS_URL || 'http://127.0.0.1:8000',
    terminalToken: process.env.LOCALPOS_TERMINAL_TOKEN || '',
    printerHost: process.env.PRINTER_HOST || '192.168.1.50',
    printerPort: Number(process.env.PRINTER_PORT || 9100),
    pollIntervalMs: Number(process.env.POLL_INTERVAL_MS || 4000),
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

function sendToPrinter(buffer) {
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

    console.log(`Agente de impresión LOCALPOS iniciado. Servidor: ${CONFIG.baseUrl}, impresora: ${CONFIG.printerHost}:${CONFIG.printerPort}`);

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
