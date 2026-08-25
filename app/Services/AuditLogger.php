<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(string $action, ?Model $auditable = null, ?array $before = null, ?array $after = null): AuditLog
    {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'business_id' => $user?->branch?->business_id ?? Business::query()->value('id'),
            'branch_id' => $user?->branch_id,
            'terminal_id' => null,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'created_at' => now(),
        ]);
    }
}
