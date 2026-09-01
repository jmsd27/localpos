<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('localpos:backup')->dailyAt('03:00');
Schedule::command('localpos:housekeeping')->dailyAt('03:30');

// sync:push como red de seguridad. SYNC_SCHEDULE_MINUTES (15 por defecto) se
// traduce a la frecuencia soportada más cercana; el envío casi inmediato lo
// hace PushSyncBatchJob si el worker de cola está activo.
$syncPush = Schedule::command('sync:push')->withoutOverlapping();

match (true) {
    ($minutes = (int) config('sync.schedule_frequency_minutes', 15)) <= 1 => $syncPush->everyMinute(),
    $minutes <= 5 => $syncPush->everyFiveMinutes(),
    $minutes <= 10 => $syncPush->everyTenMinutes(),
    $minutes <= 15 => $syncPush->everyFifteenMinutes(),
    $minutes <= 30 => $syncPush->everyThirtyMinutes(),
    default => $syncPush->hourly(),
};
