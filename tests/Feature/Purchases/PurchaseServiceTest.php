<?php

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;

function purchaseContext(): array
{
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $supplier = Supplier::factory()->create(['business_id' => $business->id]);
    $ingredient = Ingredient::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'stock' => 10]);

    return [$business, $branch, $user, $supplier, $ingredient];
}

test('crear una compra genera folio y total sin afectar el inventario todavia', function () {
    [$business, $branch, $user, $supplier, $ingredient] = purchaseContext();

    $purchase = app(PurchaseService::class)->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'items' => [
            ['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_cost' => 12.5],
        ],
    ]);

    expect($purchase->folio)->toStartWith('COMPRA-');
    expect((float) $purchase->total)->toBe(62.5);
    expect($purchase->status)->toBe(PurchaseStatus::Borrador);
    expect((float) $ingredient->fresh()->stock)->toBe(10.0);
});

test('recibir una compra aumenta el stock y actualiza el costo del insumo', function () {
    [$business, $branch, $user, $supplier, $ingredient] = purchaseContext();

    $service = app(PurchaseService::class);

    $purchase = $service->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'items' => [
            ['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_cost' => 20],
        ],
    ]);

    $service->receive($purchase, $user->id);

    expect((float) $ingredient->fresh()->stock)->toBe(15.0);
    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(20.0);
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Recibida);
});

test('no se puede recibir dos veces la misma compra', function () {
    [$business, $branch, $user, $supplier, $ingredient] = purchaseContext();

    $service = app(PurchaseService::class);

    $purchase = $service->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_cost' => 20]],
    ]);

    $service->receive($purchase, $user->id);
    $service->receive($purchase->fresh(), $user->id);
})->throws(InvalidArgumentException::class);

test('cancelar una compra recibida revierte el stock que habia ingresado', function () {
    [$business, $branch, $user, $supplier, $ingredient] = purchaseContext();

    $service = app(PurchaseService::class);

    $purchase = $service->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_cost' => 20]],
    ]);

    $service->receive($purchase, $user->id);
    expect((float) $ingredient->fresh()->stock)->toBe(15.0);

    $service->cancel($purchase->fresh(), $user->id, 'Producto en mal estado');

    expect((float) $ingredient->fresh()->stock)->toBe(10.0);
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Cancelada);
});

test('cancelar una compra en borrador no afecta el inventario', function () {
    [$business, $branch, $user, $supplier, $ingredient] = purchaseContext();

    $service = app(PurchaseService::class);

    $purchase = $service->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'items' => [['ingredient_id' => $ingredient->id, 'quantity' => 5, 'unit_cost' => 20]],
    ]);

    $service->cancel($purchase, $user->id, 'Ya no se necesita');

    expect((float) $ingredient->fresh()->stock)->toBe(10.0);
    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Cancelada);
});
