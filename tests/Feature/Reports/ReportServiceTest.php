<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\ReportService;
use App\Services\SaleService;
use Illuminate\Support\Carbon;

function sellFor(Business $business, Branch $branch, User $user, Terminal $terminal, ?int $sessionId, Product $product, float $qty, string $method): void
{
    app(SaleService::class)->complete([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
        'cash_register_session_id' => $sessionId, 'user_id' => $user->id, 'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => $qty, 'unit_price' => (float) $product->price, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => $method, 'amount' => (float) $product->price * $qty, 'received_amount' => $method === 'efectivo' ? (float) $product->price * $qty : null]],
    ]);
}

test('el resumen de ventas agrega totales, metodo de pago, productos y cajeros correctamente', function () {
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $cashRegister = CashRegister::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $terminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'cash_register_id' => $cashRegister->id]);
    $session = app(CashRegisterService::class)->open($cashRegister->id, $terminal->id, $user->id, 0);

    $burger = Product::factory()->create(['business_id' => $business->id, 'name' => 'Hamburguesa', 'price' => 100, 'tax_rate' => 0]);
    $soda = Product::factory()->create(['business_id' => $business->id, 'name' => 'Refresco', 'price' => 30, 'tax_rate' => 0]);

    sellFor($business, $branch, $user, $terminal, $session->id, $burger, 2, 'efectivo');
    sellFor($business, $branch, $user, $terminal, $session->id, $soda, 1, 'tarjeta');

    $summary = app(ReportService::class)->salesSummary($business->id, now()->startOfDay(), now()->endOfDay());

    expect($summary['orders_count'])->toBe(2);
    expect($summary['total'])->toBe(230.0);
    expect((float) $summary['by_payment_method']['efectivo'])->toBe(200.0);
    expect((float) $summary['by_payment_method']['tarjeta'])->toBe(30.0);
    expect($summary['top_products']->first()->name)->toBe('Hamburguesa');
    expect((float) $summary['by_user']->first()->total)->toBe(230.0);
});

test('el resumen de ventas no incluye ordenes fuera del rango de fechas', function () {
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $cashRegister = CashRegister::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
    $terminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'cash_register_id' => $cashRegister->id]);
    $session = app(CashRegisterService::class)->open($cashRegister->id, $terminal->id, $user->id, 0);

    $product = Product::factory()->create(['business_id' => $business->id, 'price' => 50, 'tax_rate' => 0]);
    sellFor($business, $branch, $user, $terminal, $session->id, $product, 1, 'efectivo');

    $summary = app(ReportService::class)->salesSummary(
        $business->id,
        Carbon::now()->subDays(5)->startOfDay(),
        Carbon::now()->subDays(4)->endOfDay(),
    );

    expect($summary['orders_count'])->toBe(0);
    expect($summary['total'])->toBe(0.0);
});
