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

        // El PK lo asigna la nube; el resto de columnas —timestamps y campos
        // JSON incluidos— se escriben tal cual llegaron de la sucursal.
        $model = new $modelClass;
        unset($payload[$model->getKeyName()]);

        $rewritten = $this->rewriteForeignKeys($modelType, $payload, $branchCode);

        if ($rewritten === null) {
            return false;
        }

        // Escritura cruda (query builder, no Eloquent): el espejo es una
        // copia fiel de solo lectura, así que NO se aplican casts (que
        // doble-codificarían los strings JSON de before/after de auditoría),
        // ni mutators, ni el updated_at automático, ni eventos de modelo.
        $table = $model->getTable();

        $existingMap = SyncIdMap::query()
            ->where('branch_code', $branchCode)
            ->where('model_type', $modelType)
            ->where('local_id', $localId)
            ->first();

        if ($existingMap) {
            DB::table($table)->where($model->getKeyName(), $existingMap->cloud_id)->update($rewritten);

            return true;
        }

        $cloudId = DB::table($table)->insertGetId($rewritten, $model->getKeyName());

        SyncIdMap::query()->create([
            'branch_code' => $branchCode,
            'model_type' => $modelType,
            'local_id' => $localId,
            'cloud_id' => $cloudId,
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

        return $this->rewriteMorphKeys($modelType, $payload, $branchCode);
    }

    /**
     * Referencias polimórficas (audit_logs.auditable_*, inventory_movements.reference_*):
     * el tipo se guarda como FQCN, así que se busca su clave en config('sync.models')
     * y se traduce el id vía sync_id_map. Best-effort: si el tipo no se sincroniza o
     * el padre aún no llegó, se deja el id local en vez de diferir para siempre
     * (estas filas son informativas y su referido puede haberse borrado o preceder
     * al backfill).
     */
    protected function rewriteMorphKeys(string $modelType, array $payload, string $branchCode): array
    {
        foreach (config("sync.morph_map.{$modelType}", []) as $typeColumn => $idColumn) {
            $class = $payload[$typeColumn] ?? null;
            $localId = $payload[$idColumn] ?? null;

            if (! is_string($class) || $localId === null) {
                continue;
            }

            $refModelType = $this->modelTypeForClass($class);

            if ($refModelType === null) {
                continue;
            }

            $cloudId = $this->resolveCloudId($refModelType, (int) $localId, $branchCode);

            if ($cloudId !== null) {
                $payload[$idColumn] = $cloudId;
            }
        }

        return $payload;
    }

    protected function modelTypeForClass(string $class): ?string
    {
        $class = ltrim($class, '\\');

        foreach (config('sync.models', []) as $key => $config) {
            if (ltrim((string) ($config['model'] ?? ''), '\\') === $class) {
                return $key;
            }
        }

        return null;
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
