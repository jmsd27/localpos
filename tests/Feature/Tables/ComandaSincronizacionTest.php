<?php

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableArea;

test('el catalogo offline devuelve productos activos con modificadores', function () {
    [$user] = posContext();

    $product = Product::factory()->create([
        'business_id' => $user->businessId(),
        'is_active' => true,
        'is_sellable' => true,
        'price' => 85,
    ]);

    $group = ModifierGroup::factory()->create(['business_id' => $user->businessId(), 'min_selections' => 0, 'max_selections' => 1]);
    ModifierOption::factory()->create(['modifier_group_id' => $group->id, 'price_delta' => 10]);
    $product->modifierGroups()->attach($group->id);

    Product::factory()->create(['business_id' => $user->businessId(), 'is_active' => false]);

    $response = $this->getJson(route('mesas.catalogo-offline'))->assertOk();

    $response->assertJsonPath('products.0.id', $product->id);
    $response->assertJsonPath('products.0.modifier_groups.0.options.0.price_delta', 10.0);
    $response->assertJsonCount(1, 'products');
});

test('sin permiso de ventas no se puede pedir el catalogo offline', function () {
    loginAsRole(App\Enums\RoleName::Reportes->value);

    $this->getJson(route('mesas.catalogo-offline'))->assertForbidden();
});

test('sincronizar una mesa nueva crea la orden con folio real', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 90, 'tax_rate' => 0]);

    $response = $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'client_order_uuid' => 'orden-uuid-1',
        'people_count' => 2,
        'items' => [
            ['client_item_uuid' => 'item-uuid-1', 'product_id' => $product->id, 'quantity' => 2, 'modifiers' => []],
        ],
    ])->assertOk();

    $response->assertJsonPath('order.comanda_folio', fn ($folio) => str_starts_with($folio, 'COMANDA-'));

    $order = App\Models\Order::where('client_uuid', 'orden-uuid-1')->firstOrFail();
    expect((float) $order->total)->toBe(180.0);
    expect($order->items)->toHaveCount(1);
    expect($table->fresh()->status)->toBe(App\Enums\TableStatus::Occupied);
});

test('reintentar la misma sincronizacion no duplica la orden ni los items', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50]);

    $payload = [
        'client_order_uuid' => 'orden-uuid-2',
        'items' => [
            ['client_item_uuid' => 'item-uuid-2', 'product_id' => $product->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ];

    $this->postJson(route('mesas.comanda.sincronizar', $table), $payload)->assertOk();
    $this->postJson(route('mesas.comanda.sincronizar', $table), $payload)->assertOk();

    expect(App\Models\Order::where('client_uuid', 'orden-uuid-2')->count())->toBe(1);
    expect(App\Models\OrderItem::where('client_uuid', 'item-uuid-2')->count())->toBe(1);
});

test('sincronizar agrega items a una mesa que ya tenia orden abierta', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 30]);

    Livewire\Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda');

    $order = App\Models\Order::where('table_id', $table->id)->firstOrFail();
    $segundoProducto = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 20]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'existing_order_id' => $order->id,
        'items' => [
            ['client_item_uuid' => 'item-uuid-3', 'product_id' => $segundoProducto->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ])->assertOk();

    expect((float) $order->fresh()->total)->toBe(50.0);
    expect(App\Models\Order::where('table_id', $table->id)->count())->toBe(1);
});

test('sincronizar contra una orden que ya no esta pendiente devuelve conflicto', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $order = App\Models\Order::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'table_id' => $table->id,
        'status' => App\Enums\OrderStatus::Completed,
    ]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'existing_order_id' => $order->id,
        'items' => [],
    ])->assertStatus(409);
});

test('requested_bill true pide la cuenta al sincronizar', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 40]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'client_order_uuid' => 'orden-uuid-4',
        'requested_bill' => true,
        'items' => [
            ['client_item_uuid' => 'item-uuid-4', 'product_id' => $product->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ])->assertOk();

    expect($table->fresh()->status)->toBe(App\Enums\TableStatus::ToPay);
});

test('sincronizar dispara la comanda de cocina como un envio en vivo', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $station = App\Models\KitchenStation::factory()->create(['business_id' => $user->businessId()]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'kitchen_station_id' => $station->id]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'client_order_uuid' => 'orden-uuid-5',
        'items' => [
            ['client_item_uuid' => 'item-uuid-5', 'product_id' => $product->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ])->assertOk();

    expect(App\Models\PrintJob::where('type', App\Enums\PrintJobType::ComandaCocina)->count())->toBe(1);
});

test('sin permiso de ventas no se puede sincronizar una comanda', function () {
    loginAsRole(App\Enums\RoleName::Reportes->value);
    $table = Table::factory()->create();

    $this->postJson(route('mesas.comanda.sincronizar', $table), ['items' => []])->assertForbidden();
});
