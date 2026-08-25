<?php

use App\Enums\RoleName;
use App\Models\KitchenStation;
use App\Models\Product;
use Livewire\Livewire;

test('un administrador puede crear una estacion de cocina', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.estaciones.index')
        ->call('create')
        ->set('name', 'Cocina')
        ->set('code', 'cocina')
        ->call('save')
        ->assertHasNoErrors();

    expect(KitchenStation::where('name', 'Cocina')->where('business_id', $user->businessId())->exists())->toBeTrue();
});

test('un producto puede asignarse a una estacion desde el formulario', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);

    Livewire::test('admin.productos.index')
        ->call('create')
        ->set('name', 'Hamburguesa')
        ->set('price', '100')
        ->set('kitchen_station_id', $station->id)
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Hamburguesa')->firstOrFail();
    expect($product->kitchen_station_id)->toBe($station->id);
});
