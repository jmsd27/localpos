<?php

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Terminal;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function anchoDelTicket(string $content): int
{
    $rule = collect(explode("\n", $content))
        ->first(fn ($line) => $line !== '' && str_repeat('-', strlen($line)) === $line);

    return $rule === null ? 0 : strlen($rule);
}

function cobrarTicketSimple(int $businessId, float $price = 60): PrintJob
{
    $product = Product::factory()->create([
        'business_id' => $businessId,
        'price' => $price,
        'tax_rate' => 0,
    ]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', (string) $price)
        ->set('paymentRows.0.received_amount', (string) $price)
        ->call('checkout');

    return PrintJob::where('type', 'ticket_venta')->firstOrFail();
}

test('el admin define el ancho del ticket y se usa cuando el terminal no trae ancho propio', function () {
    [$user] = posContext(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('ticket_ancho', 32)
        ->call('save')
        ->assertHasNoErrors();

    expect(anchoDelTicket(cobrarTicketSimple($user->businessId())->content))->toBe(32);
});

test('el ancho propio del terminal pisa el del negocio', function () {
    [$user, $terminal] = posContext(RoleName::Administrador->value);
    $terminal->update(['paper_width_chars' => 42]);

    Livewire::test('admin.configuracion.index')
        ->set('ticket_ancho', 32)
        ->call('save')
        ->assertHasNoErrors();

    expect(anchoDelTicket(cobrarTicketSimple($user->businessId())->content))->toBe(42);
});

test('el mensaje al pie configurado sale impreso en el ticket', function () {
    [$user] = posContext(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('ticket_pie', 'Vuelva pronto - La Martina')
        ->call('save')
        ->assertHasNoErrors();

    $content = cobrarTicketSimple($user->businessId())->content;

    expect($content)->toContain('Vuelva pronto - La Martina')
        ->and($content)->not->toContain('¡Gracias por su compra!');
});

test('un ancho de ticket invalido es rechazado', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('ticket_ancho', 40)
        ->call('save')
        ->assertHasErrors(['ticket_ancho']);
});

test('el endpoint del agente devuelve las lineas de avance configuradas', function () {
    $business = Business::factory()->create();
    app(SettingsService::class)->set($business->id, 'ticket_feed', '6', 'ticket');

    $terminal = Terminal::factory()->create(['business_id' => $business->id]);

    $this->getJson('/api/print-jobs', ['X-Terminal-Token' => $terminal->api_token])
        ->assertOk()
        ->assertJsonPath('ticket.feed_lines', 6);
});

test('al subir un logo se guarda su version ESC/POS y una vista previa', function () {
    Storage::fake('public');
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.configuracion.index')
        ->set('ticket_logo', UploadedFile::fake()->image('logo.png', 200, 80))
        ->call('save')
        ->assertHasNoErrors();

    $escpos = Setting::where('business_id', $user->businessId())->where('key', 'ticket_logo_escpos')->value('value');
    $preview = Setting::where('business_id', $user->businessId())->where('key', 'ticket_logo_preview')->value('value');

    expect($escpos)->not->toBeEmpty();
    // GS v 0 : 0x1D 0x76 0x30
    expect(substr(base64_decode($escpos), 0, 3))->toBe(chr(0x1D).'v0');
    expect($preview)->toBe("ticket-logos/{$user->businessId()}.png");
    Storage::disk('public')->assertExists($preview);
});

test('el logo viaja al agente solo cuando hay trabajos pendientes', function () {
    Storage::fake('public');
    $business = Business::factory()->create();
    app(SettingsService::class)->set($business->id, 'ticket_logo_escpos', 'QUJD', 'ticket'); // "ABC"

    $terminal = Terminal::factory()->create(['business_id' => $business->id]);

    $this->getJson('/api/print-jobs', ['X-Terminal-Token' => $terminal->api_token])
        ->assertJsonPath('ticket.logo', null);

    PrintJob::create([
        'business_id' => $business->id,
        'branch_id' => $terminal->branch_id,
        'terminal_id' => $terminal->id,
        'type' => 'ticket_venta',
        'status' => 'pendiente',
        'content' => 'hola',
    ]);

    $this->getJson('/api/print-jobs', ['X-Terminal-Token' => $terminal->api_token])
        ->assertJsonPath('ticket.logo', 'QUJD');
});

test('quitar el logo borra los ajustes y el archivo', function () {
    Storage::fake('public');
    $user = loginAsRole(RoleName::Administrador->value);

    $component = Livewire::test('admin.configuracion.index')
        ->set('ticket_logo', UploadedFile::fake()->image('logo.png', 120, 60))
        ->call('save')
        ->assertHasNoErrors();

    $preview = Setting::where('business_id', $user->businessId())->where('key', 'ticket_logo_preview')->value('value');
    Storage::disk('public')->assertExists($preview);

    $component->call('quitarLogo');

    expect(Setting::where('business_id', $user->businessId())->where('key', 'ticket_logo_escpos')->value('value'))->toBeNull();
    Storage::disk('public')->assertMissing($preview);
});
