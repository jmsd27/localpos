<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SyncOutboxEntry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Punto único de envío hacia el espejo en la nube, usado tanto por el job
 * en cola (casi inmediato) como por el comando programado sync:push (red
 * de seguridad cada 5 minutos). Ver plan: hazy-weaving-wave.md.
 */
class SyncPushService
{
    public function pushPending(): array
    {
        $cloudUrl = config('sync.cloud_url');

        if (! $cloudUrl) {
            return ['skipped' => 'no cloud_url configured', 'pushed' => 0];
        }

        $branch = $this->currentBranch();

        if (! $branch || ! $branch->sync_token) {
            return ['skipped' => 'no branch sync_token configured', 'pushed' => 0];
        }

        $entries = SyncOutboxEntry::query()
            ->pending()
            ->orderBy('id')
            ->limit((int) config('sync.push_batch_size'))
            ->get();

        if ($entries->isEmpty()) {
            return ['pushed' => 0, 'remaining' => 0];
        }

        $payload = [
            'branch_code' => $branch->code,
            'entries' => $entries->map(fn (SyncOutboxEntry $entry) => [
                'id' => $entry->id,
                'model_type' => $entry->model_type,
                'model_id' => $entry->model_id,
                'operation' => $entry->operation,
                'payload' => $entry->payload,
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];

        try {
            $response = Http::timeout((int) config('sync.push_timeout'))
                ->acceptJson()
                ->withHeaders(['X-Sync-Token' => $branch->sync_token])
                ->post(rtrim($cloudUrl, '/').'/api/sync/ingest', $payload);
        } catch (\Throwable $e) {
            $this->markFailed($entries, $e->getMessage());

            return ['pushed' => 0, 'remaining' => $entries->count(), 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            $this->markFailed($entries, "HTTP {$response->status()}: ".$response->body());

            return ['pushed' => 0, 'remaining' => $entries->count(), 'error' => "HTTP {$response->status()}"];
        }

        $acceptedIds = collect($response->json('accepted', []));
        $accepted = $entries->filter(fn (SyncOutboxEntry $entry) => $acceptedIds->contains($entry->id));
        $rejected = $entries->diff($accepted);

        if ($accepted->isNotEmpty()) {
            SyncOutboxEntry::whereIn('id', $accepted->pluck('id'))->update(['synced_at' => now()]);
        }

        if ($rejected->isNotEmpty()) {
            $this->markFailed($rejected, 'Rechazado por el endpoint de ingesta (ver "rejected" en la respuesta).');
        }

        if ($branch) {
            $branch->update(['last_synced_at' => now()]);
        }

        return ['pushed' => $accepted->count(), 'remaining' => $rejected->count()];
    }

    public function pruneSynced(): int
    {
        $cutoff = now()->subDays((int) config('sync.outbox_retention_days'));

        return SyncOutboxEntry::query()
            ->whereNotNull('synced_at')
            ->where('synced_at', '<', $cutoff)
            ->delete();
    }

    protected function markFailed($entries, string $error): void
    {
        foreach ($entries as $entry) {
            $entry->increment('attempts');
            $entry->update(['last_error' => $error]);
        }

        Log::warning('sync:push falló al empujar hacia la nube', ['error' => $error, 'entries' => $entries->count()]);
    }

    /**
     * Una instalación "source" es de un solo negocio/sucursal: se toma la
     * primera sucursal con sync_token configurado.
     */
    protected function currentBranch(): ?Branch
    {
        return Branch::query()->whereNotNull('sync_token')->first();
    }
}
