<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTerminalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Terminal-Token');

        if (! $token) {
            abort(401, 'Falta el token del terminal.');
        }

        $terminal = Terminal::query()->where('api_token', $token)->where('is_active', true)->first();

        if (! $terminal) {
            abort(401, 'Token de terminal inválido.');
        }

        $terminal->update(['last_seen_at' => now()]);

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }
}
