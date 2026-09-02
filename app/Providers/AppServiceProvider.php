<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Observers\SyncOutboxObserver;
use App\Support\LaravelLogReader;
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
        // El lector del log de Laravel apunta al archivo por defecto; los tests
        // lo sobrescriben con una ruta temporal.
        $this->app->bind(LaravelLogReader::class, fn () => LaravelLogReader::default());

        // Se registra en register() —no en boot()— a propósito: así este
        // Gate::before queda ANTES que el de spatie/laravel-permission (que
        // se engancha vía callAfterResolving(Gate) al bootear el paquete).
        // El orden importa: Gate::before corta en el primer resultado no
        // nulo, y el callback de Spatie devuelve true en cuanto el usuario
        // tiene el permiso —si corriera primero, un usuario con permiso de
        // escritura podría operar el POS contra el espejo de la nube pese al
        // rol "mirror". Ver tests/Feature/Cloud/SyncEngineTest.php.
        //
        // Es un solo callback, no dos: si el bypass de SuperAdmin estuviera en
        // un Gate::before separado y anterior, ganaría sobre el bloqueo del
        // espejo y un SuperAdmin podría escribir en la nube. En "mirror"
        // tampoco existen roles/permisos sincronizados (Spatie no está en
        // config('sync.models')), así que la lista de habilidades de solo
        // lectura decide todo por sí sola.
        Gate::before(function ($user, string $ability) {
            if (config('sync.role') === 'mirror') {
                return in_array($ability, self::MIRROR_READ_ONLY_ABILITIES, true) ? true : false;
            }

            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (config('sync.models', []) as $syncModel) {
            $syncModel['model']::observe(SyncOutboxObserver::class);
        }
    }
}
