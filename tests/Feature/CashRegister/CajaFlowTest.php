<?php

use App\Enums\RoleName;
use App\Models\CashRegister;
use App\Models\Terminal;
use Livewire\Livewire;

test('un administrador puede crear una caja', function () {
    loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.cajas.index')
        ->call('create')
        ->set('name', 'Caja Principal')
        ->set('code', 'caja-principal')
        ->call('save')
        ->assertHasNoErrors();

    expect(CashRegister::where('name', 'Caja Principal')->exists())->toBeTrue();
});

test('un cajero abre caja y accede al pos, luego la cierra', function () {
    $user = loginAsRole(RoleName::Cajero->value);

    $cashRegister = CashRegister::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $terminal = Terminal::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'cash_register_id' => $cashRegister->id,
    ]);

    session(['terminal_id' => $terminal->id]);

    Livewire::test('caja.apertura')
        ->set('opening_amount', '200')
        ->call('open')
        ->assertRedirect(route('pos'));

    expect(session('cash_register_session_id'))->not->toBeNull();

    Livewire::test('caja.cierre')
        ->set('counted_cash', '200')
        ->call('close')
        ->assertSet('closed', true);

    expect(session('cash_register_session_id'))->toBeNull();
});

test('no se puede abrir una terminal sin caja asociada', function () {
    $user = loginAsRole(RoleName::Cajero->value);

    $terminal = Terminal::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'cash_register_id' => null,
    ]);

    session(['terminal_id' => $terminal->id]);

    Livewire::test('caja.apertura')
        ->assertSet('error', fn ($error) => ! empty($error));
});
