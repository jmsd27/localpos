<?php

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Terminal;
use App\Services\PrintService;
use Livewire\Livewire;

/**
 * TASK-047 — QA independiente de TASK-046 (impresión multi-estación).
 *
 * Estos tests fijan comportamiento que ya era correcto antes de TASK-046 pero
 * no tenía cobertura dedicada (agrupación por estación, ocultamiento de
 * precios en comandas), más un caso límite del ancho de papel configurable
 * que TASK-046 no cubrió (orden sin terminal asignado en absoluto).
 */
function ruleLineOf(string $content): ?string
{
    return collect(explode("\n", $content))
        ->first(fn ($line) => $line !== '' && str_repeat('-', strlen($line)) === $line);
}

test('un pedido con items de dos estaciones distintas genera exactamente una comanda por estacion', function () {
    [$user] = posContext();

    $printerA = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $printerB = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $stationA = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'printer_terminal_id' => $printerA->id]);
    $stationB = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'printer_terminal_id' => $printerB->id]);

    $productA = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'tax_rate' => 0, 'kitchen_station_id' => $stationA->id, 'name' => 'Tacos al pastor']);
    $productB = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 45, 'tax_rate' => 0, 'kitchen_station_id' => $stationB->id, 'name' => 'Michelada']);

    Livewire::test('pos.index')
        ->call('addProduct', $productA->id)
        ->call('addProduct', $productB->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '105')
        ->set('paymentRows.0.received_amount', '105')
        ->call('checkout');

    $comandas = PrintJob::where('type', 'comanda_cocina')->get();

    // Exactamente una comanda por estación, ni una combinada ni una por producto.
    expect($comandas)->toHaveCount(2);
    expect($comandas->pluck('terminal_id')->sort()->values()->all())
        ->toBe(collect([$printerA->id, $printerB->id])->sort()->values()->all());

    $comandaA = $comandas->firstWhere('terminal_id', $printerA->id);
    $comandaB = $comandas->firstWhere('terminal_id', $printerB->id);

    // Cada comanda trae solo los items de su propia estación.
    expect($comandaA->content)->toContain('Tacos al pastor');
    expect($comandaA->content)->not->toContain('Michelada');
    expect($comandaB->content)->toContain('Michelada');
    expect($comandaB->content)->not->toContain('Tacos al pastor');
});

test('el contenido de una comanda de cocina nunca incluye precios ni montos', function () {
    [$user] = posContext();

    $printerTerminal = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $station = KitchenStation::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'printer_terminal_id' => $printerTerminal->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 123.45, 'tax_rate' => 0.16, 'kitchen_station_id' => $station->id, 'name' => 'Alambre especial']);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', number_format(123.45 * 1.16, 2, '.', ''))
        ->set('paymentRows.0.received_amount', number_format(123.45 * 1.16, 2, '.', ''))
        ->call('checkout');

    $comanda = PrintJob::where('type', 'comanda_cocina')->first();
    $ticket = PrintJob::where('type', 'ticket_venta')->first();

    expect($comanda)->not->toBeNull();
    // Ningún patrón de moneda: ni el símbolo "$" ni el precio unitario/subtotal
    // del producto (a diferencia de la cantidad, que sí es legítima en una
    // comanda y también se formatea con dos decimales — por eso no se usa un
    // regex genérico de "número con dos decimales", que daría falso positivo
    // con la cantidad).
    expect($comanda->content)->not->toContain('$');
    expect($comanda->content)->not->toContain(number_format(123.45, 2));
    expect($comanda->content)->not->toContain(number_format(123.45 * 1.16, 2));

    // Control de cordura: el ticket de venta (que sí debe llevar precios) los
    // tiene — si esto fallara, la aserción anterior sería falsa negativa.
    expect($ticket->content)->toContain('$');
    expect($ticket->content)->toContain(number_format(123.45, 2));
});

test('el ticket de venta cae a 48 columnas de respaldo cuando la orden no tiene ninguna terminal asignada', function () {
    $business = Business::factory()->create();
    $order = Order::factory()->create([
        'business_id' => $business->id,
        'terminal_id' => null,
    ]);

    $job = app(PrintService::class)->enqueueSaleTicket($order);

    $ruleLine = ruleLineOf($job->content);

    expect($order->fresh()->terminal_id)->toBeNull();
    expect($ruleLine)->not->toBeNull();
    expect(strlen($ruleLine))->toBe(48);
});

test('el formulario de Admin > Terminales rechaza un ancho de papel fuera del rango 24-64', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.terminales.index')
        ->call('create')
        ->set('name', 'Caja 99')
        ->set('code', 'caja-99')
        ->set('connection_type', 'red')
        ->set('paper_width_chars', 65)
        ->call('save')
        ->assertHasErrors(['paper_width_chars']);

    expect(Terminal::where('code', 'caja-99')->exists())->toBeFalse();
});

test('el formulario de Admin > Terminales rechaza un tipo de conexion que no sea red, usb_serial o usb_impresora', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.terminales.index')
        ->call('create')
        ->set('name', 'Caja 98')
        ->set('code', 'caja-98')
        ->set('connection_type', 'bluetooth')
        ->set('paper_width_chars', 48)
        ->call('save')
        ->assertHasErrors(['connection_type']);

    expect(Terminal::where('code', 'caja-98')->exists())->toBeFalse();
});

test('el formulario de Admin > Terminales guarda correctamente los 4 campos nuevos (modo usb_serial)', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.terminales.index')
        ->call('create')
        ->set('name', 'Impresora barra')
        ->set('code', 'barra-01')
        ->set('connection_type', 'usb_serial')
        ->set('usb_path', 'COM3')
        ->set('printer_port', 9100)
        ->set('paper_width_chars', 40)
        ->call('save')
        ->assertHasNoErrors();

    $terminal = Terminal::where('code', 'barra-01')->first();

    expect($terminal)->not->toBeNull();
    expect($terminal->connection_type)->toBe('usb_serial');
    expect($terminal->usb_path)->toBe('COM3');
    expect($terminal->paper_width_chars)->toBe(40);
});

test('el formulario de Admin > Terminales guarda correctamente el modo usb_impresora (impresora instalada en Windows)', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.terminales.index')
        ->call('create')
        ->set('name', 'Impresora cocina')
        ->set('code', 'cocina-01')
        ->set('connection_type', 'usb_impresora')
        ->set('printer_name', 'POS-80')
        ->set('paper_width_chars', 48)
        ->call('save')
        ->assertHasNoErrors();

    $terminal = Terminal::where('code', 'cocina-01')->first();

    expect($terminal)->not->toBeNull();
    expect($terminal->connection_type)->toBe('usb_impresora');
    expect($terminal->printer_name)->toBe('POS-80');
});
