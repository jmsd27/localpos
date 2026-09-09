<?php

use App\Enums\RoleName;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera un .xlsx real (no un UploadedFile::fake(), que no tiene contenido
 * parseable) para probar el importador de admin.insumos.index.
 */
function insumosSpreadsheet(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'insumos').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    // UploadedFile::fake()->createWithContent() en vez de "new UploadedFile"
    // a secas: Livewire::test()->set() con un archivo espera un objeto con
    // la propiedad pública ->name (Illuminate\Http\Testing\File la tiene),
    // pero igual necesitamos bytes reales de .xlsx para que IOFactory::load()
    // los pueda leer.
    return UploadedFile::fake()->createWithContent('insumos.xlsx', file_get_contents($path));
}

test('un administrador puede crear un insumo con existencia inicial', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.insumos.index')
        ->call('create')
        ->set('name', 'Queso mozzarella')
        ->set('unit', 'kg')
        ->set('initial_stock', '15')
        ->call('save')
        ->assertHasNoErrors();

    $ingredient = Ingredient::where('name', 'Queso mozzarella')->where('business_id', $user->businessId())->firstOrFail();
    expect((float) $ingredient->stock)->toBe(15.0);
});

test('un administrador puede armar la receta de un producto inventariable', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'is_inventoried' => true]);

    Livewire::test('admin.recetas.index')
        ->call('selectProduct', $product->id)
        ->set('ingredientId', $ingredient->id)
        ->set('quantity', '0.25')
        ->call('addItem')
        ->assertHasNoErrors();

    expect(RecipeItem::where('product_id', $product->id)->where('ingredient_id', $ingredient->id)->exists())->toBeTrue();
});

test('un movimiento manual de entrada aumenta la existencia', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 5]);

    Livewire::test('inventario.movimientos')
        ->set('ingredientId', $ingredient->id)
        ->set('type', 'entrada')
        ->set('quantity', '10')
        ->set('reason', 'Compra a proveedor')
        ->call('register')
        ->assertSet('error', null);

    expect((float) $ingredient->fresh()->stock)->toBe(15.0);
});

test('un usuario sin permiso de inventario no puede ver el kardex', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('inventario.kardex'))->assertForbidden();
});

test('el conteo físico registra un ajuste por la diferencia contada', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 10]);

    Livewire::test('inventario.conteo')
        ->set("counts.{$ingredient->id}", '7.5')
        ->call('registrar');

    expect((float) $ingredient->fresh()->stock)->toBe(7.5);
});

test('el conteo físico no genera movimiento si la cantidad contada coincide con la existencia', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 10]);

    Livewire::test('inventario.conteo')
        ->set("counts.{$ingredient->id}", '10')
        ->call('registrar')
        ->assertSet('lastResults', []);

    expect(\App\Models\InventoryMovement::where('ingredient_id', $ingredient->id)->exists())->toBeFalse();
});

test('el conteo físico ignora insumos que no se capturaron', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $counted = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 10]);
    $untouched = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 3]);

    Livewire::test('inventario.conteo')
        ->set("counts.{$counted->id}", '12')
        ->call('registrar');

    expect((float) $counted->fresh()->stock)->toBe(12.0)
        ->and((float) $untouched->fresh()->stock)->toBe(3.0);
});

test('un usuario sin permiso de inventario no puede ver el conteo físico', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('inventario.conteo'))->assertForbidden();
});

test('importar un excel crea insumos nuevos con la unidad y cantidad de cada fila', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $file = insumosSpreadsheet([
        ['Nombre', 'Unidad', 'Cantidad'],
        ['Cerveza Modelo 355ml', 'botella', '24'],
        ['Chile Curtido', 'Kilos', '5'],
    ]);

    Livewire::test('admin.insumos.index')
        ->set('importFile', $file)
        ->call('importar')
        ->assertSet('importSummary', ['created' => 2, 'adjusted' => 0, 'unchanged' => 0]);

    $cerveza = Ingredient::where('business_id', $user->businessId())->where('name', 'Cerveza Modelo 355ml')->firstOrFail();
    expect($cerveza->unit->value)->toBe('botella')
        ->and((float) $cerveza->stock)->toBe(24.0);

    $chile = Ingredient::where('business_id', $user->businessId())->where('name', 'Chile Curtido')->firstOrFail();
    expect($chile->unit->value)->toBe('kg')
        ->and((float) $chile->stock)->toBe(5.0);
});

test('importar un excel ajusta un insumo existente por la diferencia de cantidad', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $ingredient = Ingredient::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'name' => 'Papa',
        'stock' => 10,
    ]);

    $file = insumosSpreadsheet([
        ['Nombre', 'Unidad', 'Cantidad'],
        ['papa', 'kg', '15'],
    ]);

    Livewire::test('admin.insumos.index')
        ->set('importFile', $file)
        ->call('importar')
        ->assertSet('importSummary', ['created' => 0, 'adjusted' => 1, 'unchanged' => 0]);

    expect((float) $ingredient->fresh()->stock)->toBe(15.0);
    expect(InventoryMovement::where('ingredient_id', $ingredient->id)->where('reason', 'Importación desde Excel')->exists())->toBeTrue();
});

test('importar un excel no genera cambios si la cantidad esta vacia o coincide', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'name' => 'Cebolla', 'stock' => 8]);

    $file = insumosSpreadsheet([
        ['Nombre', 'Unidad', 'Cantidad'],
        ['Cebolla', 'kg', '8'],
        ['Limon', 'kg', ''],
    ]);

    Livewire::test('admin.insumos.index')
        ->set('importFile', $file)
        ->call('importar')
        ->assertSet('importSummary', ['created' => 1, 'adjusted' => 0, 'unchanged' => 1]);

    expect(Ingredient::where('business_id', $user->businessId())->where('name', 'Limon')->firstOrFail()->stock)
        ->toEqualWithDelta(0.0, 0.001);
});

test('la plantilla de insumos se puede descargar', function () {
    loginAsRole(RoleName::Administrador->value);

    $this->get(route('admin.insumos.plantilla'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertSee('Nombre,Unidad,Cantidad', false);
});

test('el reporte de inventario marca bajo stock y calcula el valor total', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    Ingredient::factory()->create([
        'business_id' => $user->businessId(), 'branch_id' => $user->branch_id,
        'name' => 'Tequila Bajo', 'stock' => 2, 'min_stock' => 5, 'cost_per_unit' => 10,
    ]);
    Ingredient::factory()->create([
        'business_id' => $user->businessId(), 'branch_id' => $user->branch_id,
        'name' => 'Tequila Normal', 'stock' => 20, 'min_stock' => 5, 'cost_per_unit' => 3,
    ]);

    Livewire::test('inventario.reportes')
        ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['total_ingredients'] === 2
            && $snapshot['low_stock_count'] === 1
            && $snapshot['total_value'] === 80.0)
        ->set('onlyLow', true)
        ->assertViewHas('ingredients', fn ($ingredients) => $ingredients->pluck('name')->all() === ['Tequila Bajo']);
});

test('el reporte de inventario resume entradas y salidas por insumo en el rango', function () {
    $user = loginAsRole(RoleName::Administrador->value);
    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'stock' => 20]);

    InventoryMovement::create([
        'business_id' => $user->businessId(), 'ingredient_id' => $ingredient->id,
        'type' => 'entrada', 'quantity' => 10, 'resulting_stock' => 30,
        'user_id' => $user->id, 'created_at' => now(),
    ]);
    InventoryMovement::create([
        'business_id' => $user->businessId(), 'ingredient_id' => $ingredient->id,
        'type' => 'consumo', 'quantity' => -4, 'resulting_stock' => 26,
        'user_id' => $user->id, 'created_at' => now(),
    ]);

    Livewire::test('inventario.reportes')
        ->assertViewHas('movements', fn ($movements) => $movements['total_entradas'] === 10.0
            && $movements['total_salidas'] === 4.0);
});

test('un usuario sin permiso de inventario no puede ver los reportes', function () {
    loginAsRole(RoleName::Mesero->value);

    $this->get(route('inventario.reportes'))->assertForbidden();
});

test('el csv de existencias de inventario se puede descargar', function () {
    $user = loginAsRole(RoleName::Administrador->value);
    Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'name' => 'Ron de prueba', 'stock' => 5]);

    $response = $this->get(route('inventario.reportes.existencias'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('Ron de prueba');
});

test('el csv de movimientos de inventario se puede descargar', function () {
    $user = loginAsRole(RoleName::Administrador->value);
    $ingredient = Ingredient::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'name' => 'Vodka de prueba', 'stock' => 5]);

    InventoryMovement::create([
        'business_id' => $user->businessId(), 'ingredient_id' => $ingredient->id,
        'type' => 'entrada', 'quantity' => 5, 'resulting_stock' => 10,
        'user_id' => $user->id, 'created_at' => now(),
    ]);

    $response = $this->get(route('inventario.reportes.movimientos', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('Vodka de prueba');
});
