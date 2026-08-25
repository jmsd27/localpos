<?php

use App\Enums\RoleName;
use App\Models\Business;
use App\Services\SettingsService;
use Livewire\Livewire;

test('un mesero no puede ver la configuracion del negocio', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('admin.configuracion'))->assertForbidden();
});

test('un administrador puede actualizar los datos del negocio', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('name', 'Restaurante Actualizado')
        ->set('phone', '555-1234')
        ->set('currency', 'usd')
        ->set('timezone', 'America/Tijuana')
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::findOrFail($user->businessId());
    expect($business->name)->toBe('Restaurante Actualizado');
    expect($business->phone)->toBe('555-1234');
    expect($business->currency)->toBe('USD');
    expect($business->timezone)->toBe('America/Tijuana');
});

test('un administrador puede cambiar la politica de inventario negativo y esta se aplica de inmediato', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('inventario_negativo', 'no_permitir')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsService::class)->get($user->businessId(), 'inventario_negativo'))->toBe('no_permitir');
});

test('el nombre del negocio es obligatorio', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
