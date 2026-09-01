<?php

/**
 * Punto de entrada de LOCALPOS cuando corre como "espejo de solo lectura"
 * (SYNC_ROLE=mirror) desplegado en Vercel sobre el runtime serverless de PHP
 * (vercel-php). Vercel enruta TODA petición no estática a este archivo
 * (ver vercel.json). No se usa en la instalación local: ahí sigue mandando
 * public/index.php servido por Laragon/Apache.
 *
 * Diferencias con public/index.php:
 *
 * 1. El sistema de archivos de Vercel es de solo lectura salvo /tmp. Laravel
 *    necesita escribir en storage/ (vistas Blade compiladas, logs, locks).
 *    Se recrea el árbol de storage bajo /tmp en cada invocación fría y se
 *    reapunta la ruta de storage de Laravel con useStoragePath().
 *
 * 2. Sesiones, caché y colas ya van a base de datos (SESSION_DRIVER=database,
 *    CACHE_STORE=database, QUEUE_CONNECTION=database), así que no dependen del
 *    disco. Los logs se mandan a stderr (LOG_CHANNEL=stderr), que Vercel
 *    captura en su panel de "Runtime Logs".
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$storagePath = '/tmp/localpos-storage';

$storageDirs = [
    $storagePath.'/app',
    $storagePath.'/app/public',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/sessions',
    $storagePath.'/framework/views',
    $storagePath.'/framework/testing',
    $storagePath.'/logs',
];

foreach ($storageDirs as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->useStoragePath($storagePath);

$app->handleRequest(Request::capture());
