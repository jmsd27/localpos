<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lógica compartida entre SyncOutboxObserver (captura en caliente) y
 * sync:backfill (carga inicial de datos preexistentes): resuelve la clave
 * corta de config('sync.models') para un modelo dado, encuentra su
 * business_id/branch_id (directo o vía relación) y arma el snapshot del
 * payload respetando exclude_fields.
 */
class SyncModelResolver
{
    public function resolveModelType(Model $model): ?string
    {
        $class = get_class($model);

        foreach (config('sync.models', []) as $key => $config) {
            if (($config['model'] ?? null) === $class) {
                return $key;
            }
        }

        return null;
    }

    public function snapshot(Model $model, array $config): array
    {
        $attributes = $model->getAttributes();

        // Se reemplaza por un valor aleatorio (no se omite la clave, ni se
        // usa un placeholder fijo): varias de estas columnas son NOT NULL
        // sin default en la BD (ej. users.password), así que quitarlas
        // rompería el insert en la nube. Un placeholder fijo tampoco sirve:
        // el cast "hashed" de User lo convertiría en un bcrypt válido y
        // *predecible*, permitiendo iniciar sesión con esa contraseña
        // conocida en cualquier usuario espejado. Random por fila = nadie
        // puede autenticarse con ella.
        foreach ($config['exclude_fields'] ?? [] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = Str::random(48);
            }
        }

        return $attributes;
    }

    /**
     * @return array{0: ?int, 1: ?int} [businessId, branchId]
     */
    public function resolveOwners(Model $model, array $config): array
    {
        $businessId = array_key_exists('business_id', $model->getAttributes())
            ? $model->getAttribute('business_id')
            : $this->resolveViaRelation($model, $config['business_via'] ?? null, 'business_id');

        $branchId = array_key_exists('branch_id', $model->getAttributes())
            ? $model->getAttribute('branch_id')
            : $this->resolveViaRelation($model, $config['branch_via'] ?? null, 'branch_id');

        return [$businessId, $branchId];
    }

    protected function resolveViaRelation(Model $model, ?string $path, string $column): ?int
    {
        if ($path === null) {
            return null;
        }

        $related = $model;

        foreach (explode('.', $path) as $segment) {
            if ($related === null) {
                return null;
            }

            $related = $related->{$segment};
        }

        return $related?->getAttribute($column);
    }
}
