<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableArea;
use App\Services\SaleService;
use Livewire\Livewire;

// --- Módulo de administración -------------------------------------------------

test('un administrador crea una cortesía y aparece en la lista', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.cortesias.index')
        ->call('create')
        ->set('code', 'CASA')
        ->set('name', 'Cortesía de la casa')
        ->set('discount_type', 'percentage')
        ->set('discount_value', '100')
        ->call('save')
        ->assertHasNoErrors();

    $coupon = Coupon::where('business_id', $user->businessId())->where('code', 'CASA')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->discount_type->value)->toBe('percentage')
        ->and((float) $coupon->discount_value)->toBe(100.0);
});

test('no se puede repetir el código de una cortesía', function () {
    $user = loginAsRole(RoleName::Administrador->value);
    Coupon::factory()->create(['business_id' => $user->businessId(), 'code' => 'AMIGO']);

    Livewire::test('admin.cortesias.index')
        ->call('create')
        ->set('code', 'amigo')
        ->set('name', 'Otra')
        ->set('discount_value', '10')
        ->call('save')
        ->assertHasErrors('code');
});

test('un porcentaje mayor a 100 se rechaza', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.cortesias.index')
        ->call('create')
        ->set('code', 'X')
        ->set('name', 'Mal')
        ->set('discount_type', 'percentage')
        ->set('discount_value', '150')
        ->call('save')
        ->assertHasErrors('discount_value');
});

test('un mesero no puede ver el módulo de cortesías', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('admin.cortesias'))->assertForbidden();
});

test('borrar una cortesía ya usada la desactiva en vez de eliminarla', function () {
    $user = loginAsRole(RoleName::Administrador->value);
    $coupon = Coupon::factory()->create(['business_id' => $user->businessId(), 'used_count' => 3]);

    Livewire::test('admin.cortesias.index')->call('delete', $coupon->id);

    expect(Coupon::find($coupon->id))->not->toBeNull()
        ->and(Coupon::find($coupon->id)->is_active)->toBeFalse();
});

// --- Aplicar la cortesía al cobrar ------------------------------------------

test('aplicar una cortesía en el POS descuenta y cuenta el uso al cobrar', function () {
    [$user] = posContext();
    $coupon = Coupon::factory()->create([
        'business_id' => $user->businessId(), 'code' => 'CASA20',
        'discount_type' => 'percentage', 'discount_value' => 20,
    ]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->set('couponCode', 'casa20')
        ->call('aplicarCortesia')
        ->assertSet('couponError', null)
        ->assertSet('couponId', $coupon->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '80')
        ->set('paymentRows.0.received_amount', '80')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order = Order::where('business_id', $user->businessId())->where('status', OrderStatus::Completed)->firstOrFail();
    expect((float) $order->discount_amount)->toBe(20.0)
        ->and((float) $order->total)->toBe(80.0)
        ->and($order->coupon_id)->toBe($coupon->id)
        ->and($coupon->fresh()->used_count)->toBe(1);
});

test('aplicar una cortesía de monto fijo en la comanda de mesa', function () {
    [$user] = posContext();
    $coupon = Coupon::factory()->amount(50)->create(['business_id' => $user->businessId(), 'code' => 'CUMPLE']);

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 200, 'tax_rate' => 0]);

    Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda')
        ->set('couponCode', 'CUMPLE')
        ->call('aplicarCortesia')
        ->assertSet('couponId', $coupon->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '150')
        ->set('paymentRows.0.received_amount', '150')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order = Order::where('table_id', $table->id)->firstOrFail();
    expect((float) $order->discount_amount)->toBe(50.0)
        ->and((float) $order->total)->toBe(150.0)
        ->and($coupon->fresh()->used_count)->toBe(1);
});

test('una cortesía desactivada no se puede aplicar', function () {
    [$user] = posContext();
    Coupon::factory()->inactive()->create(['business_id' => $user->businessId(), 'code' => 'OFF']);

    Livewire::test('pos.index')
        ->set('couponCode', 'OFF')
        ->call('aplicarCortesia')
        ->assertSet('couponId', null)
        ->assertSet('couponError', fn ($e) => str_contains((string) $e, 'desactivada'));
});

test('una cortesía vencida no se puede aplicar', function () {
    [$user] = posContext();
    Coupon::factory()->expired()->create(['business_id' => $user->businessId(), 'code' => 'VIEJA']);

    Livewire::test('pos.index')
        ->set('couponCode', 'VIEJA')
        ->call('aplicarCortesia')
        ->assertSet('couponError', fn ($e) => str_contains((string) $e, 'venció'));
});

test('una cortesía sin usos disponibles no se puede aplicar', function () {
    [$user] = posContext();
    Coupon::factory()->exhausted()->create(['business_id' => $user->businessId(), 'code' => 'GASTADA']);

    Livewire::test('pos.index')
        ->set('couponCode', 'GASTADA')
        ->call('aplicarCortesia')
        ->assertSet('couponError', fn ($e) => str_contains((string) $e, 'tope de usos'));
});

test('anular una venta con cortesía devuelve el uso', function () {
    [$user] = posContext();
    $coupon = Coupon::factory()->create(['business_id' => $user->businessId(), 'used_count' => 0]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    $order = Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->set('couponCode', $coupon->code)
        ->call('aplicarCortesia')
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '90')
        ->set('paymentRows.0.received_amount', '90')
        ->call('checkout');

    $sale = Order::where('business_id', $user->businessId())->where('status', OrderStatus::Completed)->firstOrFail();
    expect($coupon->fresh()->used_count)->toBe(1);

    app(SaleService::class)->cancel($sale, $user->id, 'prueba');

    expect($coupon->fresh()->used_count)->toBe(0);
});

test('la cortesía se re-valida al cobrar: si se agotó en el medio, el cobro falla', function () {
    [$user] = posContext();
    $coupon = Coupon::factory()->create(['business_id' => $user->businessId(), 'code' => 'LIMITE1', 'max_uses' => 1, 'used_count' => 0]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    $component = Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->set('couponCode', 'LIMITE1')
        ->call('aplicarCortesia');

    // otra venta gasta el único uso mientras esta está abierta
    $coupon->increment('used_count');

    $component
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '90')
        ->set('paymentRows.0.received_amount', '90')
        ->call('checkout')
        ->assertSet('checkoutError', fn ($e) => str_contains((string) $e, 'tope de usos'));

    expect(Order::where('business_id', $user->businessId())->where('status', OrderStatus::Completed)->count())->toBe(0);
});
