<?php

use App\Enums\RoleName;
use App\Models\CashRegister;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Product;
use App\Models\Terminal;
use App\Services\CashRegisterService;
use Livewire\Livewire;

function posContext(string $role = 'cajero'): array
{
    $user = loginAsRole($role);
    $cashRegister = CashRegister::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
    ]);
    $terminal = Terminal::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'cash_register_id' => $cashRegister->id,
    ]);
    $session = app(CashRegisterService::class)->open($cashRegister->id, $terminal->id, $user->id, 500);

    session(['terminal_id' => $terminal->id]);
    session(['cash_register_session_id' => $session->id]);

    return [$user, $terminal, $session];
}

test('sin terminal seleccionada el pos redirige al selector', function () {
    loginAsRole(RoleName::Cajero->value);

    Livewire::test('pos.index')->assertRedirect(route('pos.terminal'));
});

test('un mesero sin permiso de ventas no puede entrar al pos', function () {
    loginAsRole(RoleName::Reportes->value);

    $this->get(route('pos'))->assertForbidden();
});

test('se puede agregar un producto simple y cobrar', function () {
    [$user] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 80, 'tax_rate' => 0]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->assertSet('cart.0.name', $product->name)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '80')
        ->set('paymentRows.0.received_amount', '80')
        ->call('checkout')
        ->assertSet('checkoutError', null);

    expect(Order::where('business_id', $user->businessId())->count())->toBe(1);
});

test('un producto con modificador obligatorio no se agrega sin seleccionar opcion', function () {
    [$user] = posContext();

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100]);
    $group = ModifierGroup::factory()->create([
        'business_id' => $user->businessId(),
        'is_required' => true,
        'min_selections' => 1,
        'max_selections' => 1,
    ]);
    ModifierOption::factory()->create(['modifier_group_id' => $group->id, 'name' => 'Res', 'price_delta' => 0]);
    $product->modifierGroups()->attach($group->id);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->assertSet('modifierProductId', $product->id)
        ->call('confirmAddWithModifiers')
        ->assertSet('modifierError', fn ($error) => ! empty($error))
        ->assertSet('cart', []);
});

test('un cajero no puede ver el ticket de una venta de otro negocio', function () {
    [$user] = posContext();

    $otherOrder = Order::factory()->create();

    $this->get(route('ventas.ticket', $otherOrder))->assertForbidden();
});
