<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableArea;
use Livewire\Livewire;

test('abrir una mesa, enviar comanda y cobrarla libera la mesa', function () {
    [$user] = posContext();

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 90, 'tax_rate' => 0]);

    $component = Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->assertSet('stagedItems.0.name', $product->name)
        ->call('sendComanda');

    $table->refresh();
    expect($table->status)->toBe(TableStatus::Occupied);

    $order = Order::where('table_id', $table->id)->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->comanda_folio)->toStartWith('COMANDA-');
    expect((float) $order->total)->toBe(90.0);

    $component
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '90')
        ->set('paymentRows.0.received_amount', '90')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order->refresh();
    $table->refresh();

    expect($order->status)->toBe(OrderStatus::Completed);
    expect($order->folio)->toStartWith('VENTA-');
    expect($table->status)->toBe(TableStatus::Available);
});

test('solicitar la cuenta marca la mesa como por cobrar', function () {
    [$user] = posContext();

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50]);

    Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda')
        ->call('requestBill');

    expect($table->fresh()->status)->toBe(TableStatus::ToPay);
});

test('vaciar una mesa cancela la comanda pendiente y la libera', function () {
    [$user] = posContext();

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50]);

    Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda')
        ->call('voidTable')
        ->assertRedirect(route('mesas.mapa'));

    $order = Order::where('table_id', $table->id)->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($table->fresh()->status)->toBe(TableStatus::Available);
});

test('el mapa de mesas requiere terminal y caja abiertas', function () {
    loginAsRole(RoleName::Cajero->value);

    Livewire::test('mesas.mapa')->assertRedirect(route('pos.terminal'));
});
