<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableArea;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('SaleService calcula la propina como porcentaje del total antes de propina', function () {
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $order = app(SaleService::class)->complete([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'terminal_id' => null,
        'user_id' => $user->id,
        'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [
            ['product_id' => null, 'name' => 'Plato', 'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []],
        ],
        'discount_type' => 'amount',
        'discount_value' => 50,
        'tip_amount' => 0,
        'tip_percent' => 10,
        'payments' => [
            ['method' => 'efectivo', 'amount' => 165.0, 'received_amount' => 200.0],
        ],
    ]);

    // subtotal 200, descuento 50, base propina 150, propina 10% = 15, total 165
    expect((float) $order->tip_percent)->toBe(10.0)
        ->and((float) $order->tip_amount)->toBe(15.0)
        ->and((float) $order->total)->toBe(165.0);
});

test('el POS cobra con propina en porcentaje y la manda al ticket', function () {
    [$user, $terminal] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('tipPercent', '15')
        ->assertSet('paymentRows.0.amount', '115.00')
        ->set('paymentRows.0.amount', '115')
        ->set('paymentRows.0.received_amount', '115')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order = Order::where('business_id', $user->businessId())->latest('id')->firstOrFail();

    expect((float) $order->tip_percent)->toBe(15.0)
        ->and((float) $order->tip_amount)->toBe(15.0)
        ->and((float) $order->total)->toBe(115.0);

    $ticket = PrintJob::where('type', 'ticket_venta')->firstOrFail();
    expect($ticket->content)->toContain('Propina (15%)')
        ->and($ticket->content)->toContain('15.00');
});

test('la opcion "otro" deja poner un monto de propina libre', function () {
    [$user] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('tipPercent', 'otro')
        ->set('tipAmount', '7')
        ->set('paymentRows.0.amount', '107')
        ->set('paymentRows.0.received_amount', '107')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order = Order::where('business_id', $user->businessId())->latest('id')->firstOrFail();

    expect($order->tip_percent)->toBeNull()
        ->and((float) $order->tip_amount)->toBe(7.0)
        ->and((float) $order->total)->toBe(107.0);
});

test('si falta la migracion de tip_percent el cobro no rompe: la propina se pliega a monto', function () {
    // Simula producción con el código nuevo pero sin correr la migración.
    // OJO: el DROP COLUMN en SQLite no se revierte con la transacción del test,
    // así que lo restauramos al final para no afectar a las demás pruebas.
    Schema::table('orders', fn ($t) => $t->dropColumn('tip_percent'));

    try {
        [$user] = posContext();
        $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

        Livewire::test('pos.index')
            ->call('addProduct', $product->id)
            ->call('openCheckout')
            ->set('tipPercent', '10')
            ->set('paymentRows.0.amount', '110')
            ->set('paymentRows.0.received_amount', '110')
            ->call('checkout')
            ->assertSet('checkoutError', null);

        $order = Order::where('business_id', $user->businessId())->latest('id')->firstOrFail();

        expect((float) $order->tip_amount)->toBe(10.0)
            ->and((float) $order->total)->toBe(110.0);
    } finally {
        Schema::table('orders', fn ($t) => $t->decimal('tip_percent', 5, 2)->nullable());
    }
});

test('la comanda de mesa cobra con propina en porcentaje', function () {
    [$user] = posContext();

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda')
        ->call('openCheckout')
        ->set('tipPercent', '20')
        ->set('paymentRows.0.amount', '120')
        ->set('paymentRows.0.received_amount', '120')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order = Order::where('table_id', $table->id)->firstOrFail();

    expect((float) $order->tip_percent)->toBe(20.0)
        ->and((float) $order->tip_amount)->toBe(20.0)
        ->and((float) $order->total)->toBe(120.0);
});
