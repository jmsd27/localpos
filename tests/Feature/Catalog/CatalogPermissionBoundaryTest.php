<?php

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use Livewire\Livewire;

test('un encargado con solo productos.ver no puede crear un producto', function () {
    loginAsRole(RoleName::Encargado->value);

    Livewire::test('admin.productos.index')->call('create')->assertForbidden();
});

test('un encargado con solo productos.ver no puede editar ni eliminar un producto', function () {
    $user = loginAsRole(RoleName::Encargado->value);
    $product = Product::factory()->create(['business_id' => $user->businessId()]);

    Livewire::test('admin.productos.index')
        ->set('editingId', $product->id)
        ->set('name', $product->name)
        ->set('price', (string) $product->price)
        ->call('save')
        ->assertForbidden();

    Livewire::test('admin.productos.index')->call('delete', $product->id)->assertForbidden();

    expect($product->fresh())->not->toBeNull();
});

test('un encargado con solo productos.ver no puede crear una categoria', function () {
    loginAsRole(RoleName::Encargado->value);

    Livewire::test('admin.categorias.index')->call('create')->assertForbidden();
});

test('un encargado con solo productos.ver no puede eliminar una categoria', function () {
    $user = loginAsRole(RoleName::Encargado->value);
    $category = ProductCategory::factory()->create(['business_id' => $user->businessId()]);

    Livewire::test('admin.categorias.index')->call('delete', $category->id)->assertForbidden();

    expect($category->fresh())->not->toBeNull();
});

test('un encargado con solo productos.ver no puede crear un grupo de modificadores', function () {
    loginAsRole(RoleName::Encargado->value);

    Livewire::test('admin.modificadores.index')->call('createGroup')->assertForbidden();
});

test('un encargado con solo productos.ver no puede eliminar un grupo de modificadores', function () {
    $user = loginAsRole(RoleName::Encargado->value);
    $group = ModifierGroup::factory()->create(['business_id' => $user->businessId()]);

    Livewire::test('admin.modificadores.index')->call('deleteGroup', $group->id)->assertForbidden();

    expect($group->fresh())->not->toBeNull();
});

test('un mesero sin clientes.editar ni clientes.eliminar no puede editar ni eliminar un cliente', function () {
    $user = loginAsRole(RoleName::Mesero->value);
    $customer = Customer::factory()->create(['business_id' => $user->businessId()]);

    Livewire::test('admin.clientes.index')
        ->set('editingId', $customer->id)
        ->set('name', 'Nombre cambiado')
        ->call('save')
        ->assertForbidden();

    Livewire::test('admin.clientes.index')->call('delete', $customer->id)->assertForbidden();

    expect($customer->fresh()->name)->not->toBe('Nombre cambiado');
});
