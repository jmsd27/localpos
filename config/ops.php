<?php

return [
    /*
     * Secretos de los hooks máquina-a-máquina del espejo en la nube
     * (routes/web.php: GET /cron/housekeeping, POST /deploy/migrate).
     * Se leen desde config —no env() directo en el closure— para que sigan
     * funcionando si algún día se corre `php artisan config:cache` en Vercel.
     * Vacíos por defecto: en la instalación local los hooks quedan inertes.
     */
    'cron_secret' => env('CRON_SECRET'),
    'deploy_key' => env('DEPLOY_KEY'),
];
