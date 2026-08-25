<?php

use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SettingsService;

function inventoryContext(): array
{
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $ingredient = Ingredient::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'stock' => 10]);

    return [$business, $branch, $user, $ingredient];
}

test('una entrada aumenta el stock y una salida lo reduce', function () {
    [, , $user, $ingredient] = inventoryContext();

    $inventory = app(InventoryService::class);

    $inventory->adjustStock($ingredient, InventoryMovementType::Entrada, 5, $user->id, reason: 'Compra semanal');
    expect((float) $ingredient->fresh()->stock)->toBe(15.0);

    $inventory->adjustStock($ingredient, InventoryMovementType::Salida, -3, $user->id, reason: 'Uso interno');
    expect((float) $ingredient->fresh()->stock)->toBe(12.0);
});

test('cada movimiento registra el saldo resultante en el kardex', function () {
    [, , $user, $ingredient] = inventoryContext();

    $movement = app(InventoryService::class)->adjustStock($ingredient, InventoryMovementType::Ajuste, -2, $user->id, reason: 'Conteo físico');

    expect((float) $movement->resulting_stock)->toBe(8.0);
    expect($movement->type)->toBe(InventoryMovementType::Ajuste);
});

test('con politica no_permitir se bloquea una salida que deje el stock en negativo', function () {
    [$business, , $user, $ingredient] = inventoryContext();

    app(SettingsService::class)->set($business->id, 'inventario_negativo', 'no_permitir');

    app(InventoryService::class)->adjustStock($ingredient, InventoryMovementType::Salida, -50, $user->id, reason: 'Salida grande');
})->throws(InvalidArgumentException::class);

test('con politica permitir_alerta se permite el stock en negativo', function () {
    [, , $user, $ingredient] = inventoryContext();

    $movement = app(InventoryService::class)->adjustStock($ingredient, InventoryMovementType::Salida, -50, $user->id, reason: 'Salida grande');

    expect((float) $movement->resulting_stock)->toBe(-40.0);
});
