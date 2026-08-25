<?php

use Illuminate\Support\Facades\Route;

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
});

Route::redirect('/', '/dashboard');
