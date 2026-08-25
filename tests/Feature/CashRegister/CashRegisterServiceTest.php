<?php

use App\Enums\CashMovementType;
use App\Enums\CashRegisterSessionStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\SaleService;

function cashRegisterContext(): array
{
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $register = CashRegister::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);

    return [$business, $branch, $user, $register];
}

test('abrir una caja crea una sesion con el fondo inicial', function () {
    [, , $user, $register] = cashRegisterContext();

    $session = app(CashRegisterService::class)->open($register->id, null, $user->id, 500);

    expect($session->status)->toBe(CashRegisterSessionStatus::Open);
    expect((float) $session->opening_amount)->toBe(500.0);
});

test('no se puede abrir una caja que ya tiene una sesion abierta', function () {
    [, , $user, $register] = cashRegisterContext();

    app(CashRegisterService::class)->open($register->id, null, $user->id, 500);
    app(CashRegisterService::class)->open($register->id, null, $user->id, 300);
})->throws(InvalidArgumentException::class);

test('las ventas en efectivo suman al efectivo esperado y las de tarjeta no', function () {
    [$business, $branch, $user, $register] = cashRegisterContext();

    $cashRegisters = app(CashRegisterService::class);
    $session = $cashRegisters->open($register->id, null, $user->id, 100);

    app(SaleService::class)->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => null,
        'cash_register_session_id' => $session->id, 'user_id' => $user->id, 'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [['product_id' => null, 'name' => 'Café', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 50.0, 'received_amount' => 50.0]],
    ]);

    app(SaleService::class)->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => null,
        'cash_register_session_id' => $session->id, 'user_id' => $user->id, 'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [['product_id' => null, 'name' => 'Pastel', 'quantity' => 1, 'unit_price' => 70, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'tarjeta', 'amount' => 70.0, 'received_amount' => null]],
    ]);

    // esperado: 100 (fondo) + 50 (venta efectivo) = 150; la venta con tarjeta no suma al efectivo.
    expect($cashRegisters->expectedCash($session->fresh()))->toBe(150.0);
});

test('un retiro reduce el efectivo esperado y un ingreso lo aumenta', function () {
    [, , $user, $register] = cashRegisterContext();

    $cashRegisters = app(CashRegisterService::class);
    $session = $cashRegisters->open($register->id, null, $user->id, 200);

    $cashRegisters->addMovement($session, CashMovementType::Ingreso, 50, $user->id, reason: 'Cambio extra');
    $cashRegisters->addMovement($session, CashMovementType::Retiro, -30, $user->id, reason: 'Pago a proveedor');

    // 200 + 50 - 30 = 220
    expect($cashRegisters->expectedCash($session->fresh()))->toBe(220.0);
});

test('cerrar la caja calcula la diferencia entre lo contado y lo esperado', function () {
    [, , $user, $register] = cashRegisterContext();

    $cashRegisters = app(CashRegisterService::class);
    $session = $cashRegisters->open($register->id, null, $user->id, 100);

    $closed = $cashRegisters->close($session, 90, $user->id, 'Faltante detectado');

    expect($closed->status)->toBe(CashRegisterSessionStatus::Closed);
    expect((float) $closed->expected_cash)->toBe(100.0);
    expect((float) $closed->counted_cash)->toBe(90.0);
    expect((float) $closed->difference)->toBe(-10.0);
});

test('cancelar una venta en efectivo revierte su efecto en el efectivo esperado', function () {
    [$business, $branch, $user, $register] = cashRegisterContext();

    $cashRegisters = app(CashRegisterService::class);
    $session = $cashRegisters->open($register->id, null, $user->id, 0);

    $order = app(SaleService::class)->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => null,
        'cash_register_session_id' => $session->id, 'user_id' => $user->id, 'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [['product_id' => null, 'name' => 'Té', 'quantity' => 1, 'unit_price' => 40, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 40.0, 'received_amount' => 40.0]],
    ]);

    expect($cashRegisters->expectedCash($session->fresh()))->toBe(40.0);

    app(SaleService::class)->cancel($order, $user->id, 'Cliente se arrepintió');

    expect($cashRegisters->expectedCash($session->fresh()))->toBe(0.0);
});
