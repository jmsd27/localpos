<?php

use App\Enums\KitchenItemStatus;
use App\Enums\RoleName;
use App\Models\KitchenStation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\KitchenService;
use Livewire\Livewire;

test('un producto vendido en el pos aparece en la estacion correcta del kds', function () {
    [$user] = posContext();

    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'code' => 'cocina']);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 90, 'kitchen_station_id' => $station->id]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '90')
        ->set('paymentRows.0.received_amount', '90')
        ->call('checkout');

    $item = OrderItem::where('product_id', $product->id)->firstOrFail();

    expect($item->kitchen_station_id)->toBe($station->id);
    expect($item->kitchen_status)->toBe(KitchenItemStatus::Nuevo);

    Livewire::test('kds.tablero')
        ->call('selectStation', $station->id)
        ->assertSee($product->name)
        ->assertSee('Nuevas');
});

test('cocina puede avanzar un producto por todo el flujo hasta entregado', function () {
    [$user] = posContext();

    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'code' => 'cocina']);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50, 'kitchen_station_id' => $station->id]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '50')
        ->set('paymentRows.0.received_amount', '50')
        ->call('checkout');

    $item = OrderItem::where('product_id', $product->id)->firstOrFail();

    $kitchen = app(KitchenService::class);

    $kitchen->advance($item, KitchenItemStatus::Preparando);
    expect($item->fresh()->kitchen_status)->toBe(KitchenItemStatus::Preparando);
    expect($item->fresh()->started_at)->not->toBeNull();

    $kitchen->advance($item->fresh(), KitchenItemStatus::Listo);
    expect($item->fresh()->kitchen_status)->toBe(KitchenItemStatus::Listo);
    expect($item->fresh()->ready_at)->not->toBeNull();

    $kitchen->advance($item->fresh(), KitchenItemStatus::Entregado);
    expect($item->fresh()->kitchen_status)->toBe(KitchenItemStatus::Entregado);
    expect($item->fresh()->delivered_at)->not->toBeNull();
});

test('un usuario sin permiso de cocina no puede acceder al kds', function () {
    loginAsRole(RoleName::Reportes->value);

    $this->get(route('kds'))->assertForbidden();
});

test('el rol cocina puede avanzar productos desde el tablero', function () {
    [$adminUser] = posContext(RoleName::Administrador->value);

    $station = KitchenStation::factory()->create(['business_id' => $adminUser->businessId(), 'branch_id' => $adminUser->branch_id, 'code' => 'cocina']);
    $product = Product::factory()->create(['business_id' => $adminUser->businessId(), 'price' => 60, 'kitchen_station_id' => $station->id]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '60')
        ->set('paymentRows.0.received_amount', '60')
        ->call('checkout');

    $item = OrderItem::where('product_id', $product->id)->firstOrFail();

    $cocinaUser = loginAsRole(RoleName::Cocina->value);
    $cocinaUser->update(['branch_id' => $adminUser->branch_id]);

    Livewire::test('kds.tablero')
        ->call('selectStation', $station->id)
        ->call('advance', $item->id, 'preparando');

    expect($item->fresh()->kitchen_status)->toBe(KitchenItemStatus::Preparando);
});
