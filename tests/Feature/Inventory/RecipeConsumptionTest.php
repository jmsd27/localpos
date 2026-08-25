<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Services\SaleService;
use Livewire\Livewire;

test('vender un producto inventariable descuenta los insumos de su receta', function () {
    [$user] = posContext();

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 20]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0, 'is_inventoried' => true]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $ingredient->id, 'quantity' => 0.18]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '100')
        ->set('paymentRows.0.received_amount', '100')
        ->call('checkout');

    expect((float) $ingredient->fresh()->stock)->toBe(19.82);
});

test('vender 3 unidades multiplica el consumo de insumos por la cantidad', function () {
    [$user] = posContext();

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 20]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50, 'tax_rate' => 0, 'is_inventoried' => true]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $ingredient->id, 'quantity' => 1]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('incrementQty', 1)
        ->call('incrementQty', 1)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '150')
        ->set('paymentRows.0.received_amount', '150')
        ->call('checkout');

    expect((float) $ingredient->fresh()->stock)->toBe(17.0);
});

test('cancelar una venta devuelve los insumos consumidos al inventario', function () {
    [$user] = posContext();

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 20]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 100, 'tax_rate' => 0, 'is_inventoried' => true]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $ingredient->id, 'quantity' => 2]);

    $sales = app(SaleService::class);

    $order = $sales->complete([
        'business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'terminal_id' => session('terminal_id'),
        'cash_register_session_id' => session('cash_register_session_id'), 'user_id' => $user->id, 'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 100.0, 'received_amount' => 100.0]],
    ]);

    expect((float) $ingredient->fresh()->stock)->toBe(18.0);

    $sales->cancel($order, $user->id, 'Cliente se arrepintió');

    expect((float) $ingredient->fresh()->stock)->toBe(20.0);
});

test('un producto no inventariable no afecta el stock de insumos', function () {
    [$user] = posContext();

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 20]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 30, 'tax_rate' => 0, 'is_inventoried' => false]);
    RecipeItem::create(['product_id' => $product->id, 'ingredient_id' => $ingredient->id, 'quantity' => 5]);

    Livewire::test('pos.index')
        ->call('addProduct', $product->id)
        ->call('openCheckout')
        ->set('paymentRows.0.amount', '30')
        ->set('paymentRows.0.received_amount', '30')
        ->call('checkout');

    expect((float) $ingredient->fresh()->stock)->toBe(20.0);
});
