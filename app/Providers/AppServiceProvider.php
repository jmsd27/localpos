<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Observers\SyncOutboxObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Read-only permissions still allowed on a SYNC_ROLE=mirror instance.
     * Todo lo demás (crear/editar/eliminar/abrir/cerrar/ajustar/anular) se
     * bloquea para que nadie opere el POS contra el espejo de la nube.
     * Ver database/seeders/PermissionSeeder.php para la lista completa.
     */
    protected const MIRROR_READ_ONLY_ABILITIES = [
        'ventas.ver',
        'caja.ver_movimientos',
        'inventario.ver',
        'inventario.ver_kardex',
        'productos.ver',
        'clientes.ver',
        'compras.ver',
        'cocina.ver',
        'reportes.ver',
        'reportes.exportar',
        'configuracion.ver',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un solo callback, no dos: si estuvieran separados, el bypass de
        // SuperAdmin (registrado primero) ganaría sobre el bloqueo de
        // escritura del espejo (Gate::before corta en el primer resultado
        // no nulo) y un SuperAdmin podría operar el POS contra la nube. En
        // "mirror" tampoco existen roles/permisos sincronizados (Spatie no
        // está en config('sync.models')), así que la lista de habilidades
        // de solo lectura decide todo por sí sola, sin depender de eso.
        Gate::before(function ($user, string $ability) {
            if (config('sync.role') === 'mirror') {
                return in_array($ability, self::MIRROR_READ_ONLY_ABILITIES, true) ? true : false;
            }

            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });

        foreach (config('sync.models', []) as $syncModel) {
            $syncModel['model']::observe(SyncOutboxObserver::class);
        }
    }
}
