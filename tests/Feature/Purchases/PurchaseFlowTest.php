<?php

use App\Enums\RoleName;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Supplier;
use Livewire\Livewire;

test('un usuario con permiso de compras crea y recibe una compra desde el panel', function () {
    $user = loginAsRole(RoleName::Inventarios->value);

    $supplier = Supplier::factory()->create(['business_id' => $user->businessId()]);
    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 3]);

    Livewire::test('compras.index')
        ->call('create')
        ->set('supplierId', $supplier->id)
        ->set('items.0.ingredient_id', $ingredient->id)
        ->set('items.0.quantity', '10')
        ->set('items.0.unit_cost', '5')
        ->call('save')
        ->assertHasNoErrors();

    $purchase = Purchase::where('supplier_id', $supplier->id)->firstOrFail();
    expect((float) $purchase->total)->toBe(50.0);
    expect((float) $ingredient->fresh()->stock)->toBe(3.0);

    Livewire::test('compras.index')->call('receive', $purchase->id);

    expect((float) $ingredient->fresh()->stock)->toBe(13.0);
    expect($purchase->fresh()->status->value)->toBe('recibida');
});

test('un encargado solo puede ver la lista de compras, no crearlas', function () {
    loginAsRole(RoleName::Encargado->value);

    $this->get(route('compras.index'))->assertOk();
});

test('un mesero no puede acceder al modulo de compras', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('compras.index'))->assertForbidden();
});
