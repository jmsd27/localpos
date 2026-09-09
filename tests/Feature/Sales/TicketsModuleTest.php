<?php

use App\Enums\OrderStatus;
use App\Enums\PrintJobType;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Product;
use Livewire\Livewire;

function ventaCobrada(): array
{
    [$user] = posContext();
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 80, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '80')
        ->set('paymentRows.0.received_amount', '80')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    $order = Order::where('business_id', $user->businessId())->where('status', OrderStatus::Completed)->firstOrFail();

    return [$user, $order];
}

test('cobrar encola el ticket de venta en la cola de impresion', function () {
    [, $order] = ventaCobrada();

    $job = PrintJob::where('reference_type', (new Order)->getMorphClass())
        ->where('reference_id', $order->id)
        ->where('type', PrintJobType::TicketVenta)
        ->first();

    expect($job)->not->toBeNull()
        ->and($job->terminal_id)->toBe($order->terminal_id);
});

test('el modulo de tickets lista las ventas cobradas', function () {
    [, $order] = ventaCobrada();

    Livewire::test('tickets.index')
        ->assertOk()
        ->assertSee($order->folio);
});

test('reimprimir desde el modulo de tickets crea un PrintJob nuevo para esa venta', function () {
    [, $order] = ventaCobrada();

    $antes = PrintJob::where('reference_id', $order->id)->where('type', PrintJobType::TicketVenta)->count();

    Livewire::test('tickets.index')
        ->call('reimprimir', $order->id)
        ->assertSet('flash', fn ($f) => str_contains((string) $f, $order->folio));

    expect(PrintJob::where('reference_id', $order->id)->where('type', PrintJobType::TicketVenta)->count())->toBe($antes + 1);
});

test('reimprimir no funciona sobre una venta de otro negocio', function () {
    ventaCobrada();
    $ajeno = Order::factory()->create(['status' => OrderStatus::Completed]);

    Livewire::test('tickets.index')->call('reimprimir', $ajeno->id);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('un usuario sin permiso de ver ventas no entra al modulo de tickets', function () {
    loginAsRole(RoleName::Cocina->value);

    $this->get(route('tickets.index'))->assertForbidden();
});

test('el boton reimprimir del modal de cobro del POS encola otro ticket', function () {
    [, $order] = ventaCobrada();
    $antes = PrintJob::where('reference_id', $order->id)->where('type', PrintJobType::TicketVenta)->count();

    // reabrir el componente y simular el estado de "venta completada"
    Livewire::test('pos.index')
        ->set('completedOrderId', $order->id)
        ->set('completedFolio', $order->folio)
        ->call('reimprimirTicket');

    expect(PrintJob::where('reference_id', $order->id)->where('type', PrintJobType::TicketVenta)->count())->toBe($antes + 1);
});
