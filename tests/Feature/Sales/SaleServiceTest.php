<?php

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\User;
use App\Services\SaleService;

function saleContext(): array
{
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    return [$business, $branch, $user];
}

test('calcula subtotal, iva, descuento, propina y total correctamente', function () {
    [$business, $branch, $user] = saleContext();

    $order = app(SaleService::class)->complete([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'terminal_id' => null,
        'user_id' => $user->id,
        'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [
            ['product_id' => null, 'name' => 'Hamburguesa', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 16, 'notes' => null, 'modifiers' => []],
        ],
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'tip_amount' => 5,
        'payments' => [
            ['method' => 'efectivo', 'amount' => 217.0, 'received_amount' => 300.0],
        ],
    ]);

    // subtotal 200, descuento 10% = 20, iva 16% de 200 = 32, propina 5
    // total = 200 - 20 + 32 + 5 = 217
    expect((float) $order->subtotal)->toBe(200.0)
        ->and((float) $order->discount_amount)->toBe(20.0)
        ->and((float) $order->tax_amount)->toBe(32.0)
        ->and((float) $order->tip_amount)->toBe(5.0)
        ->and((float) $order->total)->toBe(217.0)
        ->and($order->status)->toBe(OrderStatus::Completed)
        ->and($order->folio)->toStartWith('VENTA-');
});

test('rechaza la venta si el pago es insuficiente', function () {
    [$business, $branch, $user] = saleContext();

    app(SaleService::class)->complete([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'terminal_id' => null,
        'user_id' => $user->id,
        'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [
            ['product_id' => null, 'name' => 'Café', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []],
        ],
        'discount_type' => null,
        'discount_value' => null,
        'tip_amount' => 0,
        'payments' => [
            ['method' => 'efectivo', 'amount' => 10.0, 'received_amount' => 10.0],
        ],
    ]);
})->throws(InvalidArgumentException::class);

test('calcula el cambio correctamente en pago en efectivo', function () {
    [$business, $branch, $user] = saleContext();

    $order = app(SaleService::class)->complete([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'terminal_id' => null,
        'user_id' => $user->id,
        'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [
            ['product_id' => null, 'name' => 'Cerveza', 'quantity' => 1, 'unit_price' => 45, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []],
        ],
        'discount_type' => null,
        'discount_value' => null,
        'tip_amount' => 0,
        'payments' => [
            ['method' => 'efectivo', 'amount' => 45.0, 'received_amount' => 50.0],
        ],
    ]);

    expect((float) $order->payments->first()->change_amount)->toBe(5.0);
});

test('folios son secuenciales y unicos por negocio', function () {
    [$business, $branch, $user] = saleContext();

    $service = app(SaleService::class);

    $item = ['product_id' => null, 'name' => 'Agua', 'quantity' => 1, 'unit_price' => 20, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []];

    $order1 = $service->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => null, 'user_id' => $user->id,
        'customer_id' => null, 'order_type' => 'mostrador', 'items' => [$item],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 20.0, 'received_amount' => 20.0]],
    ]);

    $order2 = $service->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => null, 'user_id' => $user->id,
        'customer_id' => null, 'order_type' => 'mostrador', 'items' => [$item],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 20.0, 'received_amount' => 20.0]],
    ]);

    expect($order1->folio)->toBe('VENTA-000001');
    expect($order2->folio)->toBe('VENTA-000002');
});

test('cancelar una venta crea un registro de cancelacion y no borra la venta', function () {
    [$business, $branch, $user] = saleContext();

    $order = app(SaleService::class)->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => null, 'user_id' => $user->id,
        'customer_id' => null, 'order_type' => 'mostrador',
        'items' => [['product_id' => null, 'name' => 'Té', 'quantity' => 1, 'unit_price' => 30, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 30.0, 'received_amount' => 30.0]],
    ]);

    $cancelled = app(SaleService::class)->cancel($order, $user->id, 'Pedido incorrecto');

    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    expect(Order::find($order->id))->not->toBeNull();
    expect(OrderCancellation::where('order_id', $order->id)->exists())->toBeTrue();
});
