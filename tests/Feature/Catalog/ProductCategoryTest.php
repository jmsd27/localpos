<?php

use App\Enums\RoleName;
use App\Models\ProductCategory;
use Livewire\Livewire;

test('un administrador puede crear una categoría', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.categorias.index')
        ->call('create')
        ->set('name', 'Bebidas')
        ->set('color', '#ff0000')
        ->call('save')
        ->assertHasNoErrors();

    expect(ProductCategory::where('name', 'Bebidas')->exists())->toBeTrue();
});

test('un mesero no puede acceder al catálogo de productos', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('admin.categorias'))->assertForbidden();
});

test('un administrador puede editar y eliminar una categoría', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $category = ProductCategory::factory()->create(['business_id' => $user->businessId()]);

    Livewire::test('admin.categorias.index')
        ->call('edit', $category->id)
        ->set('name', 'Actualizada')
        ->call('save');

    expect($category->fresh()->name)->toBe('Actualizada');

    Livewire::test('admin.categorias.index')
        ->call('delete', $category->id);

    expect(ProductCategory::find($category->id))->toBeNull();
});
