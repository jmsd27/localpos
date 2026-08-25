<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function get(int $businessId, string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            $this->cacheKey($businessId, $key),
            fn () => Setting::query()
                ->where('business_id', $businessId)
                ->where('key', $key)
                ->value('value') ?? $default,
        );
    }

    public function set(int $businessId, string $key, mixed $value, string $group = 'general'): Setting
    {
        $setting = Setting::query()->updateOrCreate(
            ['business_id' => $businessId, 'key' => $key],
            ['value' => $value, 'group' => $group],
        );

        Cache::forget($this->cacheKey($businessId, $key));

        return $setting;
    }

    private function cacheKey(int $businessId, string $key): string
    {
        return "settings:{$businessId}:{$key}";
    }
}
