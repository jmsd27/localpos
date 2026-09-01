<?php

namespace App\Console\Commands;

use App\Jobs\PushSyncBatchJob;
use App\Models\SyncOutboxEntry;
use App\Services\SyncModelResolver;
use Illuminate\Console\Command;

/**
 * Se corre UNA VEZ por instalación local al activar el sync (o cada vez que
 * se agregue un modelo nuevo a config('sync.models')): encola en el outbox
 * un "created" para cada fila que YA existía antes de que el observer
 * empezara a capturar escrituras. Sin esto, cualquier pedido que referencie
 * un usuario/terminal/producto sembrado antes de esta feature quedaría
 * diferido para siempre en el lado de ingesta (la fila padre nunca llega).
 */
class SyncBackfillCommand extends Command
{
    protected $signature = 'sync:backfill {model? : Clave de config(sync.models) a respaldar; si se omite, se procesan todos}';

    protected $description = 'Encola en sync_outbox las filas existentes que aún no se han sincronizado nunca.';

    public function handle(SyncModelResolver $resolver): int
    {
        if (config('sync.role') !== 'source') {
            $this->error('sync:backfill solo aplica en una instalación "source".');

            return self::FAILURE;
        }

        $modelKeys = $this->argument('model')
            ? [$this->argument('model')]
            : array_keys(config('sync.models', []));

        $totalQueued = 0;

        foreach ($modelKeys as $modelKey) {
            $modelConfig = config("sync.models.{$modelKey}");

            if (! $modelConfig) {
                $this->error("Modelo sincronizable desconocido: {$modelKey}");

                continue;
            }

            $modelClass = $modelConfig['model'];

            $alreadyQueued = SyncOutboxEntry::query()
                ->where('model_type', $modelKey)
                ->pluck('model_id')
                ->all();

            $queued = 0;

            $modelClass::query()
                ->whereNotIn((new $modelClass)->getKeyName(), $alreadyQueued ?: [0])
                ->orderBy((new $modelClass)->getKeyName())
                ->chunkById(200, function ($rows) use (&$queued, $modelKey, $modelConfig, $resolver) {
                    foreach ($rows as $row) {
                        [$businessId, $branchId] = $resolver->resolveOwners($row, $modelConfig);

                        SyncOutboxEntry::create([
                            'business_id' => $businessId,
                            'branch_id' => $branchId,
                            'model_type' => $modelKey,
                            'model_id' => $row->getKey(),
                            'operation' => 'created',
                            'payload' => $resolver->snapshot($row, $modelConfig),
                            'occurred_at' => now(),
                            'created_at' => now(),
                        ]);
                        $queued++;
                    }
                });

            if ($queued > 0) {
                $this->info("{$modelKey}: {$queued} fila(s) encoladas.");
            }

            $totalQueued += $queued;
        }

        if ($totalQueued > 0) {
            PushSyncBatchJob::dispatch()->afterCommit();
        }

        $this->info("Total encolado: {$totalQueued}.");

        return self::SUCCESS;
    }
}
