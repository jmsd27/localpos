<?php

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Product;
use Livewire\Livewire;

test('un rol sin permiso de ver ventas no puede acceder al historial', function () {
    loginAsRole(RoleName::Reportes->value);

    $this->get(route('ventas.index'))->assertForbidden();
});

test('el historial de ventas lista las ordenes completadas y filtra por folio', function () {
    [$user] = posContext(RoleName::Administrador->value);

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '50')
        ->set('paymentRows.0.received_amount', '50')
        ->call('checkout');

    $order = Order::where('business_id', $user->businessId())->firstOrFail();

    Livewire::test('ventas.index')
        ->assertSee($order->folio)
        ->set('folio', 'NO-EXISTE-000')
        ->assertDontSee($order->folio);
});

test('un administrador puede anular una venta completada desde el historial', function () {
    [$user] = posContext(RoleName::Administrador->value);

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 80, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '80')
        ->set('paymentRows.0.received_amount', '80')
        ->call('checkout');

    $order = Order::where('business_id', $user->businessId())->firstOrFail();

    Livewire::test('ventas.index')
        ->call('openCancel', $order->id)
        ->set('cancelReason', 'El cliente se arrepintió')
        ->call('confirmCancel')
        ->assertHasNoErrors();

    expect($order->fresh()->status->value)->toBe('cancelled');
    expect($order->fresh()->cancellation()->first()->reason)->toBe('El cliente se arrepintió');
});

test('anular sin motivo muestra un error de validacion', function () {
    [$user] = posContext(RoleName::Administrador->value);

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 80, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '80')
        ->set('paymentRows.0.received_amount', '80')
        ->call('checkout');

    $order = Order::where('business_id', $user->businessId())->firstOrFail();

    Livewire::test('ventas.index')
        ->call('openCancel', $order->id)
        ->set('cancelReason', '')
        ->call('confirmCancel')
        ->assertHasErrors(['cancelReason']);

    expect($order->fresh()->status->value)->toBe('completed');
});

test('un cajero sin permiso de anular no puede cancelar una venta aunque manipule el componente', function () {
    [$user] = posContext(RoleName::Cajero->value);

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 80, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '80')
        ->set('paymentRows.0.received_amount', '80')
        ->call('checkout');

    $order = Order::where('business_id', $user->businessId())->firstOrFail();

    Livewire::test('ventas.index')
        ->call('openCancel', $order->id)
        ->set('cancelReason', 'Intento no autorizado')
        ->call('confirmCancel')
        ->assertForbidden();

    expect($order->fresh()->status->value)->toBe('completed');
});
