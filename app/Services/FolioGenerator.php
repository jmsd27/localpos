<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class FolioGenerator
{
    /**
     * Genera el siguiente folio secuencial para el negocio y prefijo dados
     * (p. ej. 'venta' -> VENTA-000001), usando un contador atómico en `settings`.
     *
     * Si el contador quedó desincronizado y el folio calculado ya existe
     * (reimport del catálogo, restore de un respaldo, edición manual de la BD),
     * salta hacia adelante hasta encontrar uno libre en vez de reventar con
     * "Duplicate entry" al cobrar.
     */
    public function next(int $businessId, string $prefix): string
    {
        $key = "folio_seq_{$prefix}";
        $column = $prefix === 'comanda' ? 'comanda_folio' : 'folio';

        return DB::transaction(function () use ($businessId, $prefix, $key, $column) {
            $setting = Setting::query()
                ->where('business_id', $businessId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $next = $setting ? ((int) $setting->value) + 1 : 1;

            while ($this->folioExists($businessId, $column, $prefix, $next)) {
                $next++;
            }

            Setting::query()->updateOrCreate(
                ['business_id' => $businessId, 'key' => $key],
                ['value' => (string) $next, 'group' => 'folios'],
            );

            return $this->format($prefix, $next);
        });
    }

    private function folioExists(int $businessId, string $column, string $prefix, int $number): bool
    {
        return Order::query()
            ->where('business_id', $businessId)
            ->where($column, $this->format($prefix, $number))
            ->exists();
    }

    private function format(string $prefix, int $number): string
    {
        return strtoupper($prefix).'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
