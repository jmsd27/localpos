<?php

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableArea;

test('el catalogo offline devuelve productos activos con modificadores', function () {
    [$user] = posContext();

    $product = Product::factory()->create([
        'business_id' => $user->businessId(),
        'is_active' => true,
        'is_sellable' => true,
        'price' => 85,
    ]);

    $group = ModifierGroup::factory()->create(['business_id' => $user->businessId(), 'min_selections' => 0, 'max_selections' => 1]);
    ModifierOption::factory()->create(['modifier_group_id' => $group->id, 'price_delta' => 10]);
    $product->modifierGroups()->attach($group->id);

    Product::factory()->create(['business_id' => $user->businessId(), 'is_active' => false]);

    $response = $this->getJson(route('mesas.catalogo-offline'))->assertOk();

    $response->assertJsonPath('products.0.id', $product->id);
    $response->assertJsonPath('products.0.modifier_groups.0.options.0.price_delta', 10.0);
    $response->assertJsonCount(1, 'products');
});

test('sin permiso de ventas no se puede pedir el catalogo offline', function () {
    loginAsRole(App\Enums\RoleName::Reportes->value);

    $this->getJson(route('mesas.catalogo-offline'))->assertForbidden();
});
