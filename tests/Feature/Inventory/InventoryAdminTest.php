<?php

use App\Enums\RoleName;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use Livewire\Livewire;

test('un administrador puede crear un insumo con existencia inicial', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.insumos.index')
        ->call('create')
        ->set('name', 'Queso mozzarella')
        ->set('unit', 'kg')
        ->set('initial_stock', '15')
        ->call('save')
        ->assertHasNoErrors();

    $ingredient = Ingredient::where('name', 'Queso mozzarella')->where('business_id', $user->businessId())->firstOrFail();
    expect((float) $ingredient->stock)->toBe(15.0);
});

test('un administrador puede armar la receta de un producto inventariable', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'is_inventoried' => true]);

    Livewire::test('admin.recetas.index')
        ->call('selectProduct', $product->id)
        ->set('ingredientId', $ingredient->id)
        ->set('quantity', '0.25')
        ->call('addItem')
        ->assertHasNoErrors();

    expect(RecipeItem::where('product_id', $product->id)->where('ingredient_id', $ingredient->id)->exists())->toBeTrue();
});

test('un movimiento manual de entrada aumenta la existencia', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 5]);

    Livewire::test('inventario.movimientos')
        ->set('ingredientId', $ingredient->id)
        ->set('type', 'entrada')
        ->set('quantity', '10')
        ->set('reason', 'Compra a proveedor')
        ->call('register')
        ->assertSet('error', null);

    expect((float) $ingredient->fresh()->stock)->toBe(15.0);
});

test('un usuario sin permiso de inventario no puede ver el kardex', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('inventario.kardex'))->assertForbidden();
});

test('el conteo físico registra un ajuste por la diferencia contada', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 10]);

    Livewire::test('inventario.conteo')
        ->set("counts.{$ingredient->id}", '7.5')
        ->call('registrar');

    expect((float) $ingredient->fresh()->stock)->toBe(7.5);
});

test('el conteo físico no genera movimiento si la cantidad contada coincide con la existencia', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 10]);

    Livewire::test('inventario.conteo')
        ->set("counts.{$ingredient->id}", '10')
        ->call('registrar')
        ->assertSet('lastResults', []);

    expect(\App\Models\InventoryMovement::where('ingredient_id', $ingredient->id)->exists())->toBeFalse();
});

test('el conteo físico ignora insumos que no se capturaron', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $counted = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 10]);
    $untouched = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 3]);

    Livewire::test('inventario.conteo')
        ->set("counts.{$counted->id}", '12')
        ->call('registrar');

    expect((float) $counted->fresh()->stock)->toBe(12.0)
        ->and((float) $untouched->fresh()->stock)->toBe(3.0);
});

test('un usuario sin permiso de inventario no puede ver el conteo físico', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('inventario.conteo'))->assertForbidden();
});
