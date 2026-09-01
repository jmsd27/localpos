<?php

use App\Http\Middleware\AuthenticateSyncToken;
use App\Http\Middleware\AuthenticateTerminalToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'terminal.token' => AuthenticateTerminalToken::class,
            'sync.token' => AuthenticateSyncToken::class,
        ]);

        // El espejo en la nube corre detrás del proxy de Vercel: sin esto
        // Laravel genera URLs http:// y las cookies "secure" no cuadran.
        // Inofensivo en la instalación local (no llega ningún header de
        // proxy de confianza). Ver docs/03-despliegue-vercel.md.
        $middleware->trustProxies(at: '*');

        // Hooks máquina-a-máquina que se autentican con su propio secreto
        // (CRON_SECRET / DEPLOY_KEY), no con sesión web.
        $middleware->validateCsrfTokens(except: [
            'deploy/migrate',
            'cron/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
