<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Sync-Token');

        if (! $token) {
            abort(401, 'Falta el token de sincronización.');
        }

        $branch = Branch::query()->where('sync_token', $token)->first();

        if (! $branch) {
            abort(401, 'Token de sincronización inválido.');
        }

        $branch->update(['last_synced_at' => now()]);

        $request->attributes->set('sync_branch', $branch);

        return $next($request);
    }
}
