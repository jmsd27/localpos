<?php

use App\Enums\RoleName;
use App\Models\Product;
use App\Services\SaleService;

test('un usuario sin permiso de exportar recibe 403', function () {
    loginAsRole(RoleName::Cajero->value);

    $this->get(route('reportes.exportar', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->assertForbidden();
});

test('la exportacion sin fechas responde con error de validacion', function () {
    loginAsRole(RoleName::Administrador->value);

    $this->get(route('reportes.exportar'))->assertStatus(302);
});

test('el csv exportado contiene las ventas completadas del rango con encabezados correctos', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 75, 'tax_rate' => 0, 'name' => 'Torta']);

    app(SaleService::class)->complete([
        'business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'terminal_id' => null,
        'cash_register_session_id' => null, 'user_id' => $user->id, 'customer_id' => null,
        'order_type' => 'mostrador',
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => 75, 'tax_rate' => 0, 'notes' => null, 'modifiers' => []]],
        'discount_type' => null, 'discount_value' => null, 'tip_amount' => 0,
        'payments' => [['method' => 'efectivo', 'amount' => 75.0, 'received_amount' => 75.0]],
    ]);

    $response = $this->get(route('reportes.exportar', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Folio,Fecha,Cajero,Cliente,Subtotal,Descuento,IVA,Propina,Total');
    expect($content)->toContain('VENTA-000001');
    expect($content)->toContain($user->name);
    expect($content)->toContain('Efectivo');
});
