<?php

use App\Enums\RoleName;
use App\Models\Supplier;
use Livewire\Livewire;

test('un administrador puede crear un proveedor', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.proveedores.index')
        ->call('create')
        ->set('name', 'Distribuidora Norte')
        ->set('phone', '555-0100')
        ->call('save')
        ->assertHasNoErrors();

    expect(Supplier::where('name', 'Distribuidora Norte')->where('business_id', $user->businessId())->exists())->toBeTrue();
});

test('un mesero no puede acceder a proveedores', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('admin.proveedores'))->assertForbidden();
});
