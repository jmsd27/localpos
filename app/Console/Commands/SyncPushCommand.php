<?php

namespace App\Console\Commands;

use App\Services\SyncPushService;
use Illuminate\Console\Command;

class SyncPushCommand extends Command
{
    protected $signature = 'sync:push';

    protected $description = 'Empuja los cambios pendientes del outbox hacia el espejo en la nube y limpia lo ya sincronizado.';

    public function handle(SyncPushService $service): int
    {
        $result = $service->pushPending();

        if (isset($result['skipped'])) {
            $this->comment("Sin envío: {$result['skipped']}.");

            return self::SUCCESS;
        }

        $pruned = $service->pruneSynced();

        $this->info("Enviados: {$result['pushed']}. Pendientes: ".($result['remaining'] ?? 0).". Podados: {$pruned}.");

        if (! empty($result['error'])) {
            $this->error("Último error: {$result['error']}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
