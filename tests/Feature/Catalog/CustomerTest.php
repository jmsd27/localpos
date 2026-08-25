<?php

use App\Enums\RoleName;
use App\Models\Customer;
use Livewire\Livewire;

test('un cajero puede crear un cliente rápido', function () {
    loginAsRole(RoleName::Cajero->value);

    Livewire::test('admin.clientes.index')
        ->call('create')
        ->set('name', 'Cliente Frecuente')
        ->set('phone', '5551234567')
        ->call('save')
        ->assertHasNoErrors();

    expect(Customer::where('name', 'Cliente Frecuente')->exists())->toBeTrue();
});

test('un correo invalido es rechazado al crear un cliente', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.clientes.index')
        ->call('create')
        ->set('name', 'Cliente Test')
        ->set('email', 'no-es-un-correo')
        ->call('save')
        ->assertHasErrors('email');
});
