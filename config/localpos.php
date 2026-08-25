<?php

return [
    /*
     * Ruta al ejecutable mysqldump usado por el respaldo local (php artisan
     * localpos:backup). En Windows/Laragon normalmente no está en el PATH
     * del sistema, así que se configura explícitamente. Por defecto asume
     * que "mysqldump" sí está en el PATH (típico en Linux/producción).
     */
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    /*
     * Carpeta donde se guardan los respaldos .sql generados.
     */
    'backup_path' => storage_path('app/backups'),

    /*
     * Cuántos respaldos conservar; los más antiguos se eliminan al generar
     * uno nuevo desde el comando o la pantalla de administración.
     */
    'backup_retention' => (int) env('BACKUP_RETENTION', 14),
];
