<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class FolioGenerator
{
    /**
     * Genera el siguiente folio secuencial para el negocio y prefijo dados
     * (p. ej. 'venta' -> VENTA-000001), usando un contador atómico en `settings`.
     */
    public function next(int $businessId, string $prefix): string
    {
        $key = "folio_seq_{$prefix}";

        return DB::transaction(function () use ($businessId, $prefix, $key) {
            $setting = Setting::query()
                ->where('business_id', $businessId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $next = $setting ? ((int) $setting->value) + 1 : 1;

            Setting::query()->updateOrCreate(
                ['business_id' => $businessId, 'key' => $key],
                ['value' => (string) $next, 'group' => 'folios'],
            );

            return strtoupper($prefix).'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }
}
