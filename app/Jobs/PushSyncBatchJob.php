<?php

namespace App\Jobs;

use App\Services\SyncPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushSyncBatchJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function handle(SyncPushService $service): void
    {
        $service->pushPending();
    }
}
