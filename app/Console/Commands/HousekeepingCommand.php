<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza periódica de tablas operativas que crecen sin límite:
 * - sesiones expiradas (SESSION_DRIVER=database; los bots que golpean el
 *   espejo en la nube crean una fila por visita),
 * - trabajos de cola fallidos viejos,
 * - entradas de sync_outbox ya sincronizadas y fuera de la ventana de
 *   retención (solo en rol "source").
 *
 * En local la dispara el scheduler (routes/console.php). En el espejo de
 * Vercel la dispara el Cron de Vercel vía GET /cron/housekeeping (no hay un
 * proceso de scheduler persistente en serverless).
 */
class HousekeepingCommand extends Command
{
    protected $signature = 'localpos:housekeeping';

    protected $description = 'Poda sesiones expiradas, trabajos fallidos viejos y outbox ya sincronizado.';

    public function handle(): int
    {
        $this->pruneSessions();
        $this->pruneFailedJobs();
        $this->pruneOutbox();

        return self::SUCCESS;
    }

    protected function pruneSessions(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        // Doble del lifetime como colchón: una sesión "viva" nunca cae aquí.
        $cutoff = now()->subMinutes((int) config('session.lifetime', 120) * 2)->getTimestamp();

        $deleted = DB::table('sessions')->where('last_activity', '<', $cutoff)->delete();

        $this->info("Sesiones podadas: {$deleted}.");
    }

    protected function pruneFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        $deleted = DB::table('failed_jobs')
            ->where('failed_at', '<', Carbon::now()->subDays(14))
            ->delete();

        $this->info("Trabajos fallidos podados: {$deleted}.");
    }

    protected function pruneOutbox(): void
    {
        if (config('sync.role') !== 'source' || ! Schema::hasTable('sync_outbox')) {
            return;
        }

        $cutoff = now()->subDays((int) config('sync.outbox_retention_days', 30));

        $deleted = DB::table('sync_outbox')
            ->whereNotNull('synced_at')
            ->where('synced_at', '<', $cutoff)
            ->delete();

        $this->info("Outbox sincronizado podado: {$deleted}.");
    }
}
