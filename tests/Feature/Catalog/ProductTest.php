<?php

use App\Enums\RoleName;
use App\Models\Product;
use Livewire\Livewire;

test('un administrador puede crear un producto', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.productos.index')
        ->call('create')
        ->set('name', 'Hamburguesa especial')
        ->set('price', '150.00')
        ->set('unit', 'pieza')
        ->call('save')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Hamburguesa especial')->exists())->toBeTrue();
});

test('el precio es obligatorio al crear un producto', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.productos.index')
        ->call('create')
        ->set('name', 'Producto sin precio')
        ->set('price', '')
        ->call('save')
        ->assertHasErrors('price');
});

test('un producto solo lista los de su propio negocio', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Product::factory()->create(['business_id' => $user->businessId(), 'name' => 'Producto Propio']);
    Product::factory()->create(['name' => 'Producto De Otro Negocio']);

    Livewire::test('admin.productos.index')
        ->assertSee('Producto Propio')
        ->assertDontSee('Producto De Otro Negocio');
});
