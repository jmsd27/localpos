<?php

namespace App\Observers;

use App\Jobs\PushSyncBatchJob;
use App\Models\SyncOutboxEntry;
use App\Services\SyncModelResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer genérico registrado (en AppServiceProvider::boot()) para todos
 * los modelos listados en config('sync.models'). Convierte cada create/
 * update/delete en una fila de sync_outbox, que SyncPushService luego
 * empuja hacia la nube. Para datos que ya existían antes de activar el
 * sync, ver sync:backfill — este observer solo captura escrituras nuevas.
 */
class SyncOutboxObserver
{
    public function __construct(protected SyncModelResolver $resolver) {}

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    protected function record(Model $model, string $operation): void
    {
        if (config('sync.role') !== 'source') {
            return;
        }

        $modelType = $this->resolver->resolveModelType($model);

        if ($modelType === null) {
            return;
        }

        $config = config("sync.models.{$modelType}");

        [$businessId, $branchId] = $this->resolver->resolveOwners($model, $config);

        SyncOutboxEntry::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'model_type' => $modelType,
            'model_id' => $model->getKey(),
            'operation' => $operation,
            'payload' => $operation === 'deleted' ? null : $this->resolver->snapshot($model, $config),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        PushSyncBatchJob::dispatch()->afterCommit();
    }
}
