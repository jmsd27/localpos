<?php

use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\TicketController;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::livewire('/instalacion', 'instalacion.index')
    ->name('instalacion');

Route::livewire('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::livewire('/dashboard', 'dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::livewire('/ventas', 'ventas.index')
    ->middleware(['auth', 'permission:ventas.ver'])
    ->name('ventas.index');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/categorias', 'admin.categorias.index')
        ->middleware('permission:productos.ver')
        ->name('categorias');

    Route::livewire('/productos', 'admin.productos.index')
        ->middleware('permission:productos.ver')
        ->name('productos');

    Route::livewire('/modificadores', 'admin.modificadores.index')
        ->middleware('permission:productos.ver')
        ->name('modificadores');

    Route::livewire('/clientes', 'admin.clientes.index')
        ->middleware('permission:clientes.ver')
        ->name('clientes');

    Route::livewire('/configuracion', 'admin.configuracion.index')
        ->middleware('permission:configuracion.editar')
        ->name('configuracion');

    Route::livewire('/usuarios', 'admin.usuarios.index')
        ->middleware('permission:usuarios.crear')
        ->name('usuarios');

    Route::livewire('/terminales', 'admin.terminales.index')
        ->middleware('permission:configuracion.editar')
        ->name('terminales');

    Route::livewire('/cajas', 'admin.cajas.index')
        ->middleware('permission:configuracion.editar')
        ->name('cajas');

    Route::livewire('/salones', 'admin.salones.index')
        ->middleware('permission:configuracion.editar')
        ->name('salones');

    Route::livewire('/mesas', 'admin.mesas.index')
        ->middleware('permission:configuracion.editar')
        ->name('mesas');

    Route::livewire('/estaciones', 'admin.estaciones.index')
        ->middleware('permission:configuracion.editar')
        ->name('estaciones');

    Route::livewire('/insumos', 'admin.insumos.index')
        ->middleware('permission:inventario.ajustar')
        ->name('insumos');

    Route::livewire('/recetas', 'admin.recetas.index')
        ->middleware('permission:inventario.ajustar')
        ->name('recetas');

    Route::livewire('/proveedores', 'admin.proveedores.index')
        ->middleware('permission:compras.crear')
        ->name('proveedores');

    Route::livewire('/auditoria', 'admin.auditoria.index')
        ->middleware('permission:configuracion.ver')
        ->name('auditoria');

    Route::livewire('/backups', 'admin.backups.index')
        ->middleware('permission:configuracion.editar')
        ->name('backups');

    Route::get('/backups/{filename}/descargar', BackupDownloadController::class)
        ->middleware('permission:configuracion.editar')
        ->name('backups.descargar');

    Route::livewire('/menus-qr', 'admin.menus-qr.index')
        ->middleware('permission:productos.ver')
        ->name('menus-qr');

    // Manuales de implementación: solo el super administrador.
    Route::livewire('/manuales', 'admin.manuales.index')
        ->middleware('role:super-admin')
        ->name('manuales');
});

Route::middleware(['auth'])->prefix('inventario')->name('inventario.')->group(function () {
    Route::livewire('/movimientos', 'inventario.movimientos')
        ->middleware('permission:inventario.ajustar')
        ->name('movimientos');

    Route::livewire('/kardex', 'inventario.kardex')
        ->middleware('permission:inventario.ver_kardex')
        ->name('kardex');
});

Route::livewire('/kds', 'kds.tablero')
    ->middleware(['auth', 'permission:cocina.ver'])
    ->name('kds');

Route::middleware(['auth', 'permission:ventas.crear'])->group(function () {
    Route::livewire('/pos/terminal', 'pos.seleccionar-terminal')->name('pos.terminal');
    Route::livewire('/pos', 'pos.index')->name('pos');

    Route::livewire('/mesas', 'mesas.mapa')->name('mesas.mapa');
    Route::livewire('/mesas/{table}/comanda', 'mesas.comanda')->name('mesas.comanda');
});

Route::middleware(['auth'])->prefix('compras')->name('compras.')->group(function () {
    Route::livewire('/', 'compras.index')
        ->middleware('permission:compras.ver')
        ->name('index');
});

Route::middleware(['auth'])->prefix('caja')->name('caja.')->group(function () {
    Route::livewire('/apertura', 'caja.apertura')
        ->middleware('permission:caja.abrir')
        ->name('apertura');

    Route::livewire('/movimientos', 'caja.movimientos')
        ->middleware('permission:caja.ver_movimientos')
        ->name('movimientos');

    Route::livewire('/cierre', 'caja.cierre')
        ->middleware('permission:caja.cerrar')
        ->name('cierre');

    Route::livewire('/historial', 'caja.historial')
        ->middleware('permission:caja.ver_movimientos')
        ->name('historial');
});

Route::get('/ventas/{order}/ticket', TicketController::class)
    ->middleware('auth')
    ->name('ventas.ticket');

Route::livewire('/impresion/cola', 'impresion.cola')
    ->middleware(['auth', 'permission:configuracion.editar'])
    ->name('impresion.cola');

Route::livewire('/reportes', 'reportes.index')
    ->middleware(['auth', 'permission:reportes.ver'])
    ->name('reportes.index');

Route::get('/reportes/exportar', ReportExportController::class)
    ->middleware(['auth', 'permission:reportes.exportar'])
    ->name('reportes.exportar');

Route::get('/menu', PublicMenuController::class)->name('menu.show');

/*
|--------------------------------------------------------------------------
| Hooks de operación para el espejo en la nube (Vercel)
|--------------------------------------------------------------------------
| Se autentican con su propio secreto, no con sesión. En la instalación
| local quedan inertes salvo que definas CRON_SECRET / DEPLOY_KEY.
*/

// Lo llama el Cron de Vercel (envía "Authorization: Bearer <CRON_SECRET>").
Route::get('/cron/housekeeping', function (Request $request) {
    $secret = config('ops.cron_secret');

    abort_unless(
        is_string($secret) && $secret !== '' && hash_equals($secret, (string) $request->bearerToken()),
        403
    );

    Artisan::call('localpos:housekeeping');

    return response(Artisan::output(), 200)->header('Content-Type', 'text/plain');
})->name('cron.housekeeping');

// Dispara migraciones sin abrir una shell:  curl -XPOST -H "Authorization: Bearer <DEPLOY_KEY>" .../deploy/migrate
Route::post('/deploy/migrate', function (Request $request) {
    $key = config('ops.deploy_key');

    abort_unless(
        is_string($key) && $key !== '' && hash_equals($key, (string) $request->bearerToken()),
        403
    );

    Artisan::call('migrate', ['--force' => true]);

    return response(Artisan::output(), 200)->header('Content-Type', 'text/plain');
})->name('deploy.migrate');

Route::get('/', fn () => redirect()->route(Business::query()->exists() ? 'dashboard' : 'instalacion'));
