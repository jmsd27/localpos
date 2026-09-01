<?php

namespace App\Services;

use App\Models\SyncIdMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lado "mirror" del sync: aplica un lote de entradas de sync_outbox de una
 * instalación local de forma idempotente, reescribiendo foreign keys vía
 * sync_id_map (padres antes que hijos). Ver plan: hazy-weaving-wave.md.
 */
class SyncIngestionService
{
    /**
     * @return array{accepted: array<int>, rejected: array<array{id: int, reason: string}>}
     */
    public function applyBatch(string $branchCode, array $entries): array
    {
        $accepted = [];
        $rejected = [];

        foreach ($entries as $entry) {
            try {
                $applied = DB::transaction(fn () => $this->applyEntry($branchCode, $entry));

                if ($applied) {
                    $accepted[] = $entry['id'];
                } else {
                    $rejected[] = ['id' => $entry['id'], 'reason' => 'dependencia aún no sincronizada, se reintentará'];
                }
            } catch (\Throwable $e) {
                Log::warning('sync:ingest falló al aplicar una entrada', [
                    'branch_code' => $branchCode,
                    'model_type' => $entry['model_type'] ?? null,
                    'model_id' => $entry['model_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $rejected[] = ['id' => $entry['id'], 'reason' => $e->getMessage()];
            }
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    protected function applyEntry(string $branchCode, array $entry): bool
    {
        $modelType = $entry['model_type'];
        $modelConfig = config("sync.models.{$modelType}");

        if (! $modelConfig) {
            throw new \RuntimeException("Modelo sincronizable desconocido: {$modelType}");
        }

        $modelClass = $modelConfig['model'];
        $localId = (int) $entry['model_id'];

        if ($entry['operation'] === 'deleted') {
            return $this->applyDelete($modelClass, $modelType, $branchCode, $localId);
        }

        $payload = $entry['payload'] ?? [];
        unset($payload['id'], $payload['created_at'], $payload['updated_at']);

        $rewritten = $this->rewriteForeignKeys($modelType, $payload, $branchCode);

        if ($rewritten === null) {
            return false;
        }

        $existingMap = SyncIdMap::query()
            ->where('branch_code', $branchCode)
            ->where('model_type', $modelType)
            ->where('local_id', $localId)
            ->first();

        if ($existingMap) {
            $modelClass::query()->whereKey($existingMap->cloud_id)->update($rewritten);

            return true;
        }

        $created = $modelClass::query()->create($rewritten);

        SyncIdMap::query()->create([
            'branch_code' => $branchCode,
            'model_type' => $modelType,
            'local_id' => $localId,
            'cloud_id' => $created->getKey(),
        ]);

        return true;
    }

    protected function applyDelete(string $modelClass, string $modelType, string $branchCode, int $localId): bool
    {
        $map = SyncIdMap::query()
            ->where('branch_code', $branchCode)
            ->where('model_type', $modelType)
            ->where('local_id', $localId)
            ->first();

        if (! $map) {
            // Nunca llegó a existir en la nube; no hay nada que borrar.
            return true;
        }

        $instance = $modelClass::query()->find($map->cloud_id);

        if ($instance && method_exists($instance, 'trashed')) {
            $instance->delete();
        }

        // Modelos sin soft delete: se conserva la fila en el espejo a
        // propósito (ver callout de riesgos en el plan) en vez de borrarla
        // físicamente, ya que la nube funciona como histórico/reporting.
        return true;
    }

    /**
     * @return array<string, mixed>|null null si falta resolver una FK aún no sincronizada
     */
    protected function rewriteForeignKeys(string $modelType, array $payload, string $branchCode): ?array
    {
        $fkMap = config("sync.fk_map.{$modelType}", []);

        foreach ($fkMap as $column => $refModelType) {
            if (! array_key_exists($column, $payload) || $payload[$column] === null) {
                continue;
            }

            $cloudId = $this->resolveCloudId($refModelType, (int) $payload[$column], $branchCode);

            if ($cloudId === null) {
                return null;
            }

            $payload[$column] = $cloudId;
        }

        return $payload;
    }

    protected function resolveCloudId(string $modelType, int $localId, string $branchCode): ?int
    {
        return SyncIdMap::query()
            ->where('branch_code', $branchCode)
            ->where('model_type', $modelType)
            ->where('local_id', $localId)
            ->value('cloud_id');
    }
}
