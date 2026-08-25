<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'localpos:backup';

    protected $description = 'Genera un respaldo .sql de la base de datos (vía mysqldump) y aplica la política de retención.';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Respaldo generado: {$result['filename']} ({$result['size']} bytes).");

        return self::SUCCESS;
    }
}
