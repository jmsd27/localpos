<?php

namespace App\Services;

use App\Support\LaravelLogReader;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reúne señales de salud de la instalación para el panel de "Diagnóstico y
 * asistencia" (Administración → Asistencia, solo super admin).
 *
 * Todo es de SOLO LECTURA: no ejecuta comandos, no escribe archivos y nunca
 * expone valores del .env (solo versiones y banderas booleanas). Cada bloque
 * está aislado en try/catch para que un fallo puntual no tumbe el panel.
 */
class DiagnosticsService
{
    public function __construct(
        private readonly LaravelLogReader $log,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'app' => $this->app(),
            'database' => $this->database(),
            'queue' => $this->queue(),
            'storage' => $this->storage(),
            'sync' => $this->sync(),
            'logs' => $this->logs(),
        ];
    }

    /** @return array<string, mixed> */
    private function app(): array
    {
        return [
            'name' => config('app.name'),
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'php_version' => PHP_VERSION,
            'laravel_version' => App::version(),
            'sync_role' => config('sync.role'),
            'config_cached' => app()->configurationIsCached(),
            'timezone' => config('app.timezone'),
        ];
    }

    /** @return array<string, mixed> */
    private function database(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();

            $pending = null;

            try {
                $migrator = app('migrator');
                $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
                $ran = $migrator->getRepository()->getRan();
                $pending = count(array_diff($files, $ran));
            } catch (Throwable) {
                // Repositorio de migraciones ausente: se deja en null.
            }

            return [
                'connected' => true,
                'driver' => $connection->getDriverName(),
                'name' => $connection->getDatabaseName(),
                'pending_migrations' => $pending,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'driver' => config('database.default'),
                'name' => null,
                'pending_migrations' => null,
                'error' => $this->oneLine($e->getMessage()),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function queue(): array
    {
        $out = ['pending' => null, 'failed' => null, 'failed_recent' => []];

        try {
            if (Schema::hasTable('jobs')) {
                $out['pending'] = DB::table('jobs')->count();
            }

            if (Schema::hasTable('failed_jobs')) {
                $out['failed'] = DB::table('failed_jobs')->count();
                $out['failed_recent'] = DB::table('failed_jobs')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(['id', 'queue', 'exception', 'failed_at'])
                    ->map(fn ($row) => [
                        'id' => $row->id,
                        'queue' => $row->queue,
                        'failed_at' => (string) $row->failed_at,
                        'exception' => $this->oneLine((string) $row->exception),
                    ])
                    ->all();
            }
        } catch (Throwable $e) {
            $out['error'] = $this->oneLine($e->getMessage());
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function storage(): array
    {
        $out = [
            'logs_bytes' => $this->log->fileBytes(),
            'disk_free_bytes' => null,
            'disk_total_bytes' => null,
        ];

        try {
            $free = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $out['disk_free_bytes'] = $free === false ? null : (int) $free;
            $out['disk_total_bytes'] = $total === false ? null : (int) $total;
        } catch (Throwable) {
            // Algunos hostings compartidos deshabilitan disk_*_space().
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function sync(): array
    {
        $out = ['role' => config('sync.role')];

        try {
            if (! Schema::hasTable('sync_outbox')) {
                return $out;
            }

            $pending = DB::table('sync_outbox')->whereNull('synced_at');

            $out['outbox_pending'] = (clone $pending)->count();
            $out['outbox_with_errors'] = (clone $pending)->whereNotNull('last_error')->count();
            $out['oldest_pending_at'] = (clone $pending)->min('occurred_at');
            $out['last_synced_at'] = DB::table('sync_outbox')->whereNotNull('synced_at')->max('synced_at');
        } catch (Throwable $e) {
            $out['error'] = $this->oneLine($e->getMessage());
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function logs(): array
    {
        return [
            'exists' => $this->log->fileExists(),
            'bytes' => $this->log->fileBytes(),
            'errors' => $this->log->recentErrors(),
        ];
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_substr($text, 0, 300)) ?? '');
    }
}
