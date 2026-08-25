<?php

use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\TicketController;
use App\Models\Business;
use Illuminate\Support\Facades\Route;

Route::livewire('/instalacion', 'instalacion.index')
    ->name('instalacion');

Route::livewire('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::livewire('/dashboard', 'dashboard')
    ->middleware('auth')
    ->name('dashboard');

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

Route::get('/', fn () => redirect()->route(Business::query()->exists() ? 'dashboard' : 'instalacion'));
