<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Terminal;
use App\Models\User;
use App\Services\CashRegisterService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * Un mesero no tiene el permiso caja.abrir (correcto: no debería poder abrir
 * una caja). Pero si esa caja YA está abierta —por un cajero o admin, en otro
 * dispositivo— el mesero sí tiene que poder usar mesas/POS desde el suyo: solo
 * hace falta enganchar su sesión de navegador a la caja ya abierta, no pedirle
 * el permiso de abrirla. Antes de este fix, mount() mandaba a /caja/apertura
 * sin fijarse si ya había una sesión abierta, y esa ruta exige caja.abrir —
 * el mesero quedaba bloqueado con un 403 aunque la caja funcionara bien para
 * cualquier otra persona en otro terminal/sesión.
 */
function meseroConCajaYaAbiertaPorOtroUsuario(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(PermissionSeeder::class);
    test()->seed(RoleSeeder::class);

    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $cashRegister = CashRegister::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $terminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'cash_register_id' => $cashRegister->id]);

    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole(RoleName::Administrador->value);
    $session = app(CashRegisterService::class)->open($cashRegister->id, $terminal->id, $admin->id, 500);

    $mesero = User::factory()->create(['branch_id' => $branch->id]);
    $mesero->assignRole(RoleName::Mesero->value);
    test()->actingAs($mesero);

    expect($mesero->can('caja.abrir'))->toBeFalse();

    session(['terminal_id' => $terminal->id]);
    session()->forget('cash_register_session_id');

    return [$mesero, $terminal, $session];
}

test('un mesero se engancha a la caja ya abierta por otro usuario al entrar al mapa de mesas', function () {
    [, , $session] = meseroConCajaYaAbiertaPorOtroUsuario();

    Livewire::test('mesas.mapa')->assertOk();

    expect(session('cash_register_session_id'))->toBe($session->id);
});

test('un mesero se engancha a la caja ya abierta por otro usuario al entrar a una comanda', function () {
    [$mesero, , $session] = meseroConCajaYaAbiertaPorOtroUsuario();

    $area = \App\Models\TableArea::factory()->create(['business_id' => $mesero->businessId(), 'branch_id' => $mesero->branch_id]);
    $table = \App\Models\Table::factory()->create(['business_id' => $mesero->businessId(), 'branch_id' => $mesero->branch_id, 'table_area_id' => $area->id]);

    Livewire::test('mesas.comanda', ['table' => $table])->assertOk();

    expect(session('cash_register_session_id'))->toBe($session->id);
});

test('un mesero se engancha a la caja ya abierta por otro usuario al entrar al pos', function () {
    [, , $session] = meseroConCajaYaAbiertaPorOtroUsuario();

    Livewire::test('pos.index')->assertOk();

    expect(session('cash_register_session_id'))->toBe($session->id);
});

test('sin ninguna caja abierta el mesero sigue mandado a abrir caja (y ahi lo bloquea el permiso)', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(PermissionSeeder::class);
    test()->seed(RoleSeeder::class);

    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $cashRegister = CashRegister::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $terminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'cash_register_id' => $cashRegister->id]);

    $mesero = User::factory()->create(['branch_id' => $branch->id]);
    $mesero->assignRole(RoleName::Mesero->value);
    test()->actingAs($mesero);

    session(['terminal_id' => $terminal->id]);
    session()->forget('cash_register_session_id');

    Livewire::test('mesas.mapa')->assertRedirect(route('caja.apertura'));

    $this->get(route('caja.apertura'))->assertForbidden();
});
