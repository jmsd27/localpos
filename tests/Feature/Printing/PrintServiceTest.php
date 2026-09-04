<?php

use App\Models\KitchenStation;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Terminal;
use App\Services\PrintService;
use Livewire\Livewire;

test('vender un producto con estacion encola una comanda para el terminal impresor de esa estacion', function () {
    [$user, $terminal] = posContext();

    $printerTerminal = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'printer_terminal_id' => $printerTerminal->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'tax_rate' => 0, 'kitchen_station_id' => $station->id]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '60')
        ->set('paymentRows.0.received_amount', '60')
        ->call('checkout');

    $comanda = PrintJob::where('type', 'comanda_cocina')->first();

    expect($comanda)->not->toBeNull();
    expect($comanda->terminal_id)->toBe($printerTerminal->id);
    expect($comanda->content)->toContain($product->name);
});

test('un producto sin estacion no genera comanda impresa', function () {
    [$user] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'tax_rate' => 0, 'kitchen_station_id' => null]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '60')
        ->set('paymentRows.0.received_amount', '60')
        ->call('checkout');

    expect(PrintJob::where('type', 'comanda_cocina')->exists())->toBeFalse();
});

test('cobrar en efectivo encola el ticket con apertura de cajon activada', function () {
    [$user] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '100')
        ->set('paymentRows.0.received_amount', '100')
        ->call('checkout');

    $ticket = PrintJob::where('type', 'ticket_venta')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->open_drawer)->toBeTrue();
    expect($ticket->content)->toContain('TOTAL');
});

test('cobrar solo con tarjeta no activa la apertura de cajon', function () {
    [$user] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.method', 'tarjeta')
        ->set('paymentRows.0.amount', '100')
        ->call('checkout');

    $ticket = PrintJob::where('type', 'ticket_venta')->first();

    expect($ticket->open_drawer)->toBeFalse();
});

test('el ticket de venta usa el ancho de papel configurado en el terminal', function () {
    [$user, $terminal] = posContext();
    $terminal->update(['paper_width_chars' => 32]);

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '60')
        ->set('paymentRows.0.received_amount', '60')
        ->call('checkout');

    $ticket = PrintJob::where('type', 'ticket_venta')->first();
    $ruleLine = collect(explode("\n", $ticket->content))->first(fn ($line) => $line !== '' && str_repeat('-', strlen($line)) === $line);

    expect($ruleLine)->not->toBeNull();
    expect(strlen($ruleLine))->toBe(32);
});

test('la comanda de cocina usa el ancho de papel del terminal impresor de la estacion', function () {
    [$user] = posContext();

    $printerTerminal = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'paper_width_chars' => 40]);
    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'printer_terminal_id' => $printerTerminal->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'tax_rate' => 0, 'kitchen_station_id' => $station->id]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '60')
        ->set('paymentRows.0.received_amount', '60')
        ->call('checkout');

    $comanda = PrintJob::where('type', 'comanda_cocina')->first();
    $ruleLine = collect(explode("\n", $comanda->content))->first(fn ($line) => $line !== '' && str_repeat('-', strlen($line)) === $line);

    expect($ruleLine)->not->toBeNull();
    expect(strlen($ruleLine))->toBe(40);
});

test('la comanda de cocina usa 48 columnas de respaldo si la estacion no tiene terminal impresor', function () {
    [$user] = posContext();

    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'printer_terminal_id' => null]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'tax_rate' => 0, 'kitchen_station_id' => $station->id]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '60')
        ->set('paymentRows.0.received_amount', '60')
        ->call('checkout');

    $comanda = PrintJob::where('type', 'comanda_cocina')->first();
    $ruleLine = collect(explode("\n", $comanda->content))->first(fn ($line) => $line !== '' && str_repeat('-', strlen($line)) === $line);

    expect($ruleLine)->not->toBeNull();
    expect(strlen($ruleLine))->toBe(48);
});

test('abrir el cajon manualmente encola un trabajo de apertura', function () {
    [$user, $terminal] = posContext();

    $job = app(PrintService::class)->openCashDrawer($terminal->id, $user->id);

    expect($job->type->value)->toBe('apertura_cajon');
    expect($job->open_drawer)->toBeTrue();
    expect($job->status->value)->toBe('pendiente');
});

test('marcar un trabajo como impreso y luego reintentar un trabajo con error', function () {
    [$user, $terminal] = posContext();

    $printer = app(PrintService::class);
    $job = $printer->openCashDrawer($terminal->id, $user->id);

    $printer->markFailed($job, 'Impresora sin papel');
    $job->refresh();
    expect($job->status->value)->toBe('error');
    expect($job->error_message)->toBe('Impresora sin papel');
    expect($job->attempts)->toBe(1);

    $printer->retry($job);
    $job->refresh();
    expect($job->status->value)->toBe('pendiente');
    expect($job->error_message)->toBeNull();

    $printer->markPrinted($job);
    $job->refresh();
    expect($job->status->value)->toBe('impreso');
    expect($job->printed_at)->not->toBeNull();
});
