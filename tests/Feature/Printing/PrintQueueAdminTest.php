<?php

use App\Enums\PrintJobType;
use App\Enums\RoleName;
use App\Models\PrintJob;
use App\Models\Terminal;
use Livewire\Livewire;

test('un mesero no puede acceder a la cola de impresion', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('impresion.cola'))->assertForbidden();
});

test('un administrador puede reintentar un trabajo con error', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $terminal = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $job = PrintJob::create([
        'business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'terminal_id' => $terminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'error', 'error_message' => 'Sin papel', 'content' => 'x',
    ]);

    Livewire::test('impresion.cola')->call('retry', $job->id);

    expect($job->fresh()->status->value)->toBe('pendiente');
});

test('un administrador puede encolar la apertura manual del cajon', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $terminal = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);

    Livewire::test('impresion.cola')
        ->set('drawerTerminalId', $terminal->id)
        ->call('openDrawer')
        ->assertHasNoErrors();

    expect(PrintJob::where('type', 'apertura_cajon')->where('terminal_id', $terminal->id)->exists())->toBeTrue();
});

test('un administrador puede regenerar el token de una terminal', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $terminal = Terminal::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'api_token' => 'token-viejo']);

    Livewire::test('admin.terminales.index')->call('regenerateToken', $terminal->id);

    expect($terminal->fresh()->api_token)->not->toBe('token-viejo');
});
