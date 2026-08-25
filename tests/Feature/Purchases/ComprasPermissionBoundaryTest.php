<?php

use App\Enums\RoleName;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Livewire\Livewire;

test('un encargado con solo compras.ver no puede crear una compra', function () {
    loginAsRole(RoleName::Encargado->value);

    Livewire::test('compras.index')->call('create')->assertForbidden();
});

test('un encargado con solo compras.ver no puede recibir ni cancelar una compra', function () {
    $user = loginAsRole(RoleName::Encargado->value);

    $supplier = Supplier::factory()->create(['business_id' => $user->businessId()]);
    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);

    $purchase = app(PurchaseService::class)->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_cost' => 10]],
    ]);

    Livewire::test('compras.index')->call('receive', $purchase->id)->assertForbidden();
    Livewire::test('compras.index')->call('openCancel', $purchase->id)->assertForbidden();

    expect($purchase->fresh()->status->value)->toBe('borrador');
});
