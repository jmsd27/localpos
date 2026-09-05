# Modo offline de operación (mesas/comanda) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar que Bar La Martina siga tomando comandas de mesas cuando se corta el internet del dominio (sin servidor local), sin generar folio ni tocar caja/cobro hasta que la conexión vuelve, reutilizando `SaleService` tal cual existe hoy.

**Architecture:** Un endpoint nuevo e idempotente (`POST /mesas/{table}/comanda/sincronizar`) reproduce, cuando vuelve la conexión, las mismas llamadas a `SaleService::createDraftOrder`/`addItemsToOrder`/`requestBill` que ya usa `mesas.comanda` hoy. Mientras no hay conexión, un módulo JS liviano (sin librerías nuevas) guarda un borrador por mesa en `localStorage` usando un catálogo cacheado, y un panel Alpine reemplaza visualmente al componente Livewire de comanda (que sigue intacto para el caso normal, en línea).

**Tech Stack:** Laravel 12 (controller + migraciones), Pest (tests), Alpine.js (ya viene con Livewire), JS vanilla + `localStorage` (sin librerías nuevas), Vite.

**Spec:** `docs/superpowers/specs/2026-09-05-modo-offline-comandas-design.md`

## Global Constraints

- No se toca la lógica de negocio existente en `SaleService`: solo se le agrega el paso de `client_uuid` a dos métodos ya existentes.
- El endpoint de sincronización usa autenticación de sesión normal (`auth` + `permission:ventas.crear`), no un token de máquina.
- Sin folio (`comanda_folio`) hasta que la sincronización realmente corre en el servidor — nunca se genera un folio provisorio en el cliente.
- Caja (abrir/movimientos/cerrar) y Cobrar quedan bloqueados sin conexión; no se construye ninguna variante offline de esos flujos.
- No se agregan librerías JS nuevas (nada de Dexie/IndexedDB wrappers): `localStorage` + `fetch` alcanza para el volumen de datos de un bar.
- El banner "sin conexión" del espejo (`SYNC_ROLE=mirror`, ver `MirrorOfflineTest.php`) no se toca ni se unifica con este mecanismo nuevo — son mecanismos separados para roles distintos.
- Todo cambio de servidor lleva test Pest; la capa de JS/`localStorage` se valida a mano en el navegador (no hay test runner de JS en este repo) siguiendo el procedimiento del Task 10.
- El panel offline (Task 7) no soporta modificadores: los productos con grupos de modificadores obligatorios se pueden agregar igual (sin abrir el selector), quedan sin modificador elegido. Es una simplificación deliberada del "funciones principales nomás" pedido — si hace falta, es una mejora futura separada, no parte de este plan.
- Cancelar ventas y las pantallas de Administración/Reportes **no** llevan ningún cambio de deshabilitado explícito: son páginas Livewire completas a las que solo se llega navegando, y sin conexión esa navegación ya falla sola a nivel del navegador (no hay nada cacheado para servir, a diferencia del espejo). El Task 9 solo cubre Caja porque sus tres pantallas sí pueden quedar abiertas y usables (con el botón de acción) mientras el usuario está en otra pestaña/mesa cuando se corta la conexión.

---

## Mapa de archivos

| Archivo | Qué hace |
|---|---|
| `database/migrations/2026_09_06_000001_add_client_uuid_to_orders_table.php` | Columna `client_uuid` nullable + única por negocio en `orders`. |
| `database/migrations/2026_09_06_000002_add_client_uuid_to_order_items_table.php` | Columna `client_uuid` nullable + única por orden en `order_items`. |
| `app/Models/Order.php` | Agrega `client_uuid` a `$fillable`. |
| `app/Models/OrderItem.php` | Agrega `client_uuid` a `$fillable`. |
| `app/Services/SaleService.php` | `createDraftOrder`/`addItemsToOrder` guardan `client_uuid` si viene. |
| `app/Http/Controllers/OfflineComandaController.php` (nuevo) | `catalogo()` y `sincronizar()`. |
| `routes/web.php` | Dos rutas nuevas dentro del grupo `permission:ventas.crear`. |
| `resources/views/layouts/app.blade.php` | Meta `csrf-token` para los `fetch()`. |
| `resources/js/offline-connectivity.js` (nuevo) | Store Alpine `offline` + heartbeat a `/up`. |
| `resources/js/offline-comanda.js` (nuevo) | Caché de catálogo, borradores por mesa, cola de sincronización. |
| `resources/js/app.js` | Importa los dos módulos nuevos. |
| `resources/views/components/mesas/⚡comanda.blade.php` | Panel offline (Alpine) al lado del contenido Livewire existente. |
| `resources/views/components/mesas/⚡mapa.blade.php` | Indicador "pendiente de sincronizar" por mesa. |
| `resources/views/components/caja/⚡apertura.blade.php`, `⚡movimientos.blade.php`, `⚡cierre.blade.php` | Deshabilitan su botón principal sin conexión. |
| `tests/Feature/Sales/SaleServiceTest.php` | Cubre `client_uuid` en `createDraftOrder`. |
| `tests/Feature/Tables/ComandaSincronizacionTest.php` (nuevo) | Cubre el endpoint completo. |

---

### Task 1: `client_uuid` en `orders`/`order_items` + soporte en `SaleService`

**Files:**
- Create: `database/migrations/2026_09_06_000001_add_client_uuid_to_orders_table.php`
- Create: `database/migrations/2026_09_06_000002_add_client_uuid_to_order_items_table.php`
- Modify: `app/Models/Order.php`
- Modify: `app/Models/OrderItem.php`
- Modify: `app/Services/SaleService.php:62-89` (`createDraftOrder`), `:94-127` (`addItemsToOrder`)
- Test: `tests/Feature/Sales/SaleServiceTest.php`

**Interfaces:**
- Produces: `Order::create([..., 'client_uuid' => ?string])`, `$order->items()->create([..., 'client_uuid' => ?string])` — ambos opcionales, `null` por defecto, usados por el controlador del Task 4.

- [ ] **Step 1: Escribir el test que falla**

Agregar al final de `tests/Feature/Sales/SaleServiceTest.php` (revisar primero el `use` de arriba del archivo para reusar los mismos helpers que ya usa; si no importa `Ingredient`/`SaleService` explícitamente, usar `app(SaleService::class)`):

```php
test('createDraftOrder guarda el client_uuid cuando se manda', function () {
    [$user, $terminal, $session] = posContext();

    $order = app(App\Services\SaleService::class)->createDraftOrder([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'terminal_id' => $terminal->id,
        'cash_register_session_id' => $session->id,
        'user_id' => $user->id,
        'table_id' => null,
        'order_type' => 'mesa',
        'client_uuid' => 'draft-uuid-123',
    ]);

    expect($order->client_uuid)->toBe('draft-uuid-123');
});

test('dos ordenes del mismo negocio no pueden repetir client_uuid', function () {
    [$user] = posContext();

    App\Models\Order::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'client_uuid' => 'draft-uuid-dup',
    ]);

    expect(fn () => App\Models\Order::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'client_uuid' => 'draft-uuid-dup',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

`posContext()` está definido en `tests/Feature/Sales/PosTest.php` y ya es visible para toda la suite Pest (mismo patrón que usa `tests/Feature/Tables/ComandaFlowTest.php`).

- [ ] **Step 2: Correr el test y confirmar que falla**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=SaleServiceTest
```
Esperado: FAIL — `client_uuid` no existe todavía en `orders` ni en `$fillable`.

- [ ] **Step 3: Migraciones**

`database/migrations/2026_09_06_000001_add_client_uuid_to_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('client_uuid')->nullable()->after('comanda_folio');
            $table->unique(['business_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
```

`database/migrations/2026_09_06_000002_add_client_uuid_to_order_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('client_uuid')->nullable()->after('order_id');
            $table->unique(['order_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
```

- [ ] **Step 4: Modelos**

En `app/Models/Order.php`, agregar `'client_uuid',` al arreglo `$fillable` (justo después de `'comanda_folio',`).

En `app/Models/OrderItem.php`, agregar `'client_uuid',` al arreglo `$fillable` (justo después de `'order_id',`).

- [ ] **Step 5: `SaleService`**

En `app/Services/SaleService.php`, dentro de `createDraftOrder()` (la llamada a `Order::create([...])`), agregar después de `'comanda_folio' => ...,`:

```php
            'client_uuid' => $data['client_uuid'] ?? null,
```

Dentro de `addItemsToOrder()`, en la llamada `$order->items()->create([...])`, agregar después de `'product_id' => $item['product_id'],`:

```php
                    'client_uuid' => $item['client_uuid'] ?? null,
```

- [ ] **Step 6: Migrar y correr el test**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=SaleServiceTest
```
Esperado: PASS.

- [ ] **Step 7: Correr toda la suite (no debe romper nada existente)**

```bash
php artisan test
```
Esperado: todos los tests existentes siguen en verde (los que llaman `createDraftOrder`/`addItemsToOrder` sin `client_uuid` no cambian de comportamiento porque el campo es opcional).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_06_000001_add_client_uuid_to_orders_table.php \
        database/migrations/2026_09_06_000002_add_client_uuid_to_order_items_table.php \
        app/Models/Order.php app/Models/OrderItem.php app/Services/SaleService.php \
        tests/Feature/Sales/SaleServiceTest.php
git commit -m "puntoYA: agrega client_uuid a orders/order_items para sincronizar comandas offline"
```

---

### Task 2: Meta CSRF token en el layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php:3-7` (dentro de `<head>`)

**Interfaces:**
- Produces: `<meta name="csrf-token" content="...">` en el `<head>` de toda página autenticada — lo consume `resources/js/offline-comanda.js` (Task 6) para mandar `X-CSRF-TOKEN` en el `POST` de sincronización (la ruta usa el middleware `web` normal, con CSRF activo).

- [ ] **Step 1: Agregar el meta tag**

En `resources/views/layouts/app.blade.php`, dentro de `<head>`, justo debajo de `<meta name="viewport" ...>`:

```blade
        <meta name="csrf-token" content="{{ csrf_token() }}">
```

- [ ] **Step 2: Verificar manualmente**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=MirrorOfflineTest
```
Esperado: PASS (este test ya revisa contenido del `<head>`, confirma que no rompiste el layout).

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "puntoYA: agrega meta csrf-token para las llamadas fetch del modo offline"
```

---

### Task 3: Endpoint de catálogo offline (`GET /mesas/catalogo-offline`)

**Files:**
- Create: `app/Http/Controllers/OfflineComandaController.php` (método `catalogo()`; `sincronizar()` se agrega en el Task 4)
- Modify: `routes/web.php:131-137` (grupo `permission:ventas.crear`)
- Test: `tests/Feature/Tables/ComandaSincronizacionTest.php` (nuevo)

**Interfaces:**
- Produces: `GET /mesas/catalogo-offline` → JSON `{generated_at, products: [{id,name,price,tax_rate,product_category_id,kitchen_station_id,modifier_groups:[{id,name,min_selections,max_selections,options:[{id,name,price_delta}]}]}], categories: [{id,name}], tables: [{id,name,status}]}`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/Tables/ComandaSincronizacionTest.php`:

```php
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
```

`ModifierGroup` se relaciona con `Product` por muchos-a-muchos (tabla pivote `product_modifier_group`, ver `Product::modifierGroups()` y `ModifierGroup::products()`), por eso el test usa `attach()` en vez de un `product_id` directo.

- [ ] **Step 2: Correr el test y confirmar que falla**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=ComandaSincronizacionTest
```
Esperado: FAIL — la ruta `mesas.catalogo-offline` no existe.

- [ ] **Step 3: Controlador**

Crear `app/Http/Controllers/OfflineComandaController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OfflineComandaController extends Controller
{
    /**
     * Catálogo mínimo para que el navegador pueda armar comandas sin
     * conexión: productos vendibles/activos con sus modificadores, y el
     * estado actual de las mesas. Se pide una vez al cargar mesas/mapa o
     * mesas/comanda y se refresca solo cada pocos minutos (ver
     * resources/js/offline-comanda.js).
     */
    public function catalogo(): JsonResponse
    {
        $businessId = Auth::user()->businessId();

        $products = Product::query()
            ->where('business_id', $businessId)
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->with('modifierGroups.options')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'tax_rate' => (float) $product->tax_rate,
                'product_category_id' => $product->product_category_id,
                'kitchen_station_id' => $product->kitchen_station_id,
                'modifier_groups' => $product->modifierGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'min_selections' => $group->min_selections,
                    'max_selections' => $group->max_selections,
                    'options' => $group->options->map(fn ($option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => (float) $option->price_delta,
                    ]),
                ]),
            ]);

        $categories = ProductCategory::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tables = Table::query()
            ->where('business_id', $businessId)
            ->get(['id', 'name', 'status']);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'products' => $products,
            'categories' => $categories,
            'tables' => $tables,
        ]);
    }
}
```

- [ ] **Step 4: Ruta**

En `routes/web.php`, dentro del grupo que ya tiene `/pos`, `/mesas`, agregar antes del `});` de cierre:

```php
    Route::get('/mesas/catalogo-offline', [App\Http\Controllers\OfflineComandaController::class, 'catalogo'])
        ->name('mesas.catalogo-offline');
```

(Agregar `use App\Http\Controllers\OfflineComandaController;` arriba del archivo junto a los demás `use App\Http\Controllers\...` y usar `OfflineComandaController::class` directo, siguiendo el estilo ya usado para `BackupDownloadController`/`TicketController`.)

- [ ] **Step 5: Correr el test**

```bash
php artisan test --filter=ComandaSincronizacionTest
```
Esperado: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OfflineComandaController.php routes/web.php tests/Feature/Tables/ComandaSincronizacionTest.php
git commit -m "puntoYA: endpoint de catalogo offline para armar comandas sin conexion"
```

---

### Task 4: Endpoint de sincronización (`POST /mesas/{table}/comanda/sincronizar`)

**Files:**
- Modify: `app/Http/Controllers/OfflineComandaController.php` (agrega `sincronizar()` y `resolveOrder()`)
- Modify: `routes/web.php` (la misma zona del Task 3)
- Test: `tests/Feature/Tables/ComandaSincronizacionTest.php`

**Interfaces:**
- Consumes: `SaleService::createDraftOrder(array $data)`, `addItemsToOrder(Order $order, array $items)`, `requestBill(Order $order)` — firmas ya existentes, sin cambios más allá del Task 1.
- Produces: `POST /mesas/{table}/comanda/sincronizar` con body `{client_order_uuid?, existing_order_id?, people_count?, requested_bill?, items: [{client_item_uuid, product_id, quantity, notes?, modifiers?: [{modifier_option_id?, name, price_delta}]}]}` → `200 {"order": {...}}` | `409` (conflicto) | `422` (validación) | `403` (permiso).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/Tables/ComandaSincronizacionTest.php`:

```php
test('sincronizar una mesa nueva crea la orden con folio real', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 90, 'tax_rate' => 0]);

    $response = $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'client_order_uuid' => 'orden-uuid-1',
        'people_count' => 2,
        'items' => [
            ['client_item_uuid' => 'item-uuid-1', 'product_id' => $product->id, 'quantity' => 2, 'modifiers' => []],
        ],
    ])->assertOk();

    $response->assertJsonPath('order.comanda_folio', fn ($folio) => str_starts_with($folio, 'COMANDA-'));

    $order = App\Models\Order::where('client_uuid', 'orden-uuid-1')->firstOrFail();
    expect((float) $order->total)->toBe(180.0);
    expect($order->items)->toHaveCount(1);
    expect($table->fresh()->status)->toBe(App\Enums\TableStatus::Occupied);
});

test('reintentar la misma sincronizacion no duplica la orden ni los items', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 50]);

    $payload = [
        'client_order_uuid' => 'orden-uuid-2',
        'items' => [
            ['client_item_uuid' => 'item-uuid-2', 'product_id' => $product->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ];

    $this->postJson(route('mesas.comanda.sincronizar', $table), $payload)->assertOk();
    $this->postJson(route('mesas.comanda.sincronizar', $table), $payload)->assertOk();

    expect(App\Models\Order::where('client_uuid', 'orden-uuid-2')->count())->toBe(1);
    expect(App\Models\OrderItem::where('client_uuid', 'item-uuid-2')->count())->toBe(1);
});

test('sincronizar agrega items a una mesa que ya tenia orden abierta', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 30]);

    Livewire\Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda');

    $order = App\Models\Order::where('table_id', $table->id)->firstOrFail();
    $segundoProducto = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 20]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'existing_order_id' => $order->id,
        'items' => [
            ['client_item_uuid' => 'item-uuid-3', 'product_id' => $segundoProducto->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ])->assertOk();

    expect((float) $order->fresh()->total)->toBe(50.0);
    expect(App\Models\Order::where('table_id', $table->id)->count())->toBe(1);
});

test('sincronizar contra una orden que ya no esta pendiente devuelve conflicto', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $order = App\Models\Order::factory()->create([
        'business_id' => $user->businessId(),
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'table_id' => $table->id,
        'status' => App\Enums\OrderStatus::Completed,
    ]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'existing_order_id' => $order->id,
        'items' => [],
    ])->assertStatus(409);
});

test('requested_bill true pide la cuenta al sincronizar', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 40]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'client_order_uuid' => 'orden-uuid-4',
        'requested_bill' => true,
        'items' => [
            ['client_item_uuid' => 'item-uuid-4', 'product_id' => $product->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ])->assertOk();

    expect($table->fresh()->status)->toBe(App\Enums\TableStatus::ToPay);
});

test('sincronizar dispara la comanda de cocina como un envio en vivo', function () {
    [$user] = posContext();
    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $station = App\Models\KitchenStation::factory()->create(['business_id' => $user->businessId()]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 60, 'kitchen_station_id' => $station->id]);

    $this->postJson(route('mesas.comanda.sincronizar', $table), [
        'client_order_uuid' => 'orden-uuid-5',
        'items' => [
            ['client_item_uuid' => 'item-uuid-5', 'product_id' => $product->id, 'quantity' => 1, 'modifiers' => []],
        ],
    ])->assertOk();

    expect(App\Models\PrintJob::where('type', App\Enums\PrintJobType::ComandaCocina)->count())->toBe(1);
});

test('sin permiso de ventas no se puede sincronizar una comanda', function () {
    loginAsRole(App\Enums\RoleName::Reportes->value);
    $table = Table::factory()->create();

    $this->postJson(route('mesas.comanda.sincronizar', $table), ['items' => []])->assertForbidden();
});
```

`KitchenStationFactory` crea su propia sucursal internamente y acepta que se le pise `business_id` en el `create([...])` (igual que otras factories del repo); no hace falta pasarle `branch_id` para este test porque `PrintService::enqueueKitchenComandas` no valida que la estación pertenezca a la misma sucursal de la orden.

- [ ] **Step 2: Correr los tests y confirmar que fallan**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=ComandaSincronizacionTest
```
Esperado: FAIL — la ruta `mesas.comanda.sincronizar` no existe.

- [ ] **Step 3: Agregar `sincronizar()` y `resolveOrder()` al controlador**

Agregar a `app/Http/Controllers/OfflineComandaController.php` (imports adicionales arriba: `App\Enums\OrderStatus`, `App\Models\Order`, `App\Services\SaleService`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\DB`):

```php
    public function sincronizar(Request $request, Table $table, SaleService $sales): JsonResponse
    {
        $user = Auth::user();
        abort_unless($table->business_id === $user->businessId(), 404);

        $data = $request->validate([
            'client_order_uuid' => ['nullable', 'string'],
            'existing_order_id' => ['nullable', 'integer'],
            'people_count' => ['nullable', 'integer', 'min:1'],
            'requested_bill' => ['boolean'],
            'items' => ['array'],
            'items.*.client_item_uuid' => ['required', 'string'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.modifiers' => ['array'],
            'items.*.modifiers.*.modifier_option_id' => ['nullable', 'integer'],
            'items.*.modifiers.*.name' => ['required', 'string'],
            'items.*.modifiers.*.price_delta' => ['required', 'numeric'],
        ]);

        $products = Product::query()
            ->whereIn('id', collect($data['items'] ?? [])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($data, $table, $user, $sales, $products) {
            $order = $this->resolveOrder($data, $table, $user, $sales);

            $newItems = collect($data['items'] ?? [])
                ->reject(fn ($item) => $order->items()->where('client_uuid', $item['client_item_uuid'])->exists())
                ->map(function ($item) use ($products) {
                    $product = $products->get($item['product_id']);

                    return [
                        'product_id' => $item['product_id'],
                        'client_uuid' => $item['client_item_uuid'],
                        'kitchen_station_id' => $product?->kitchen_station_id,
                        'name' => $product?->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => (float) $product?->price,
                        'tax_rate' => (float) $product?->tax_rate,
                        'notes' => $item['notes'] ?? null,
                        'modifiers' => $item['modifiers'] ?? [],
                    ];
                })
                ->values()
                ->all();

            if ($newItems !== []) {
                $sales->addItemsToOrder($order, $newItems);
            }

            if ($data['requested_bill'] ?? false) {
                $sales->requestBill($order);
            }

            return response()->json(['order' => $order->fresh(['items.modifiers'])]);
        });
    }

    private function resolveOrder(array $data, Table $table, $user, SaleService $sales): Order
    {
        if (! empty($data['existing_order_id'])) {
            $order = Order::query()
                ->where('id', $data['existing_order_id'])
                ->where('table_id', $table->id)
                ->where('business_id', $table->business_id)
                ->first();

            if (! $order || $order->status !== OrderStatus::Pending) {
                abort(409, 'La mesa ya no tiene una comanda abierta para sincronizar.');
            }

            return $order;
        }

        if (! empty($data['client_order_uuid'])) {
            $existing = Order::query()
                ->where('business_id', $table->business_id)
                ->where('client_uuid', $data['client_order_uuid'])
                ->first();

            if ($existing) {
                return $existing;
            }

            return $sales->createDraftOrder([
                'business_id' => $table->business_id,
                'branch_id' => $table->branch_id,
                'terminal_id' => session('terminal_id'),
                'cash_register_session_id' => session('cash_register_session_id'),
                'user_id' => $user->id,
                'table_id' => $table->id,
                'people_count' => $data['people_count'] ?? null,
                'order_type' => 'mesa',
                'client_uuid' => $data['client_order_uuid'],
            ]);
        }

        abort(422, 'Falta client_order_uuid o existing_order_id.');
    }
```

- [ ] **Step 4: Ruta**

En `routes/web.php`, junto a la ruta del Task 3:

```php
    Route::post('/mesas/{table}/comanda/sincronizar', [OfflineComandaController::class, 'sincronizar'])
        ->name('mesas.comanda.sincronizar');
```

- [ ] **Step 5: Correr los tests**

```bash
php artisan test --filter=ComandaSincronizacionTest
```
Esperado: PASS en los 9 tests del archivo (2 del catálogo + 7 de sincronización).

- [ ] **Step 6: Correr toda la suite**

```bash
php artisan test
```
Esperado: todo en verde.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/OfflineComandaController.php routes/web.php tests/Feature/Tables/ComandaSincronizacionTest.php
git commit -m "puntoYA: endpoint idempotente para sincronizar comandas armadas sin conexion"
```

---

### Task 5: Módulo JS de conectividad (heartbeat + store Alpine)

**Files:**
- Create: `resources/js/offline-connectivity.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Produces: `Alpine.store('offline').online` (boolean, reactivo) — lo consumen el Task 7 (panel offline), Task 8 (indicador del mapa) y Task 9 (botones de caja).

- [ ] **Step 1: Crear el módulo**

`resources/js/offline-connectivity.js`:

```js
// Detecta si el navegador puede hablar con el servidor, no solo si el
// dispositivo tiene wifi/datos: navigator.onLine no alcanza (puede haber
// wifi sin que el servidor responda). Expone Alpine.store('offline').online,
// que consumen el panel offline de mesas/comanda, el indicador del mapa de
// mesas y los botones de caja.
const HEARTBEAT_INTERVAL_MS = 15000;

function setOnline(online) {
    if (window.Alpine?.store('offline')) {
        window.Alpine.store('offline').online = online;
    }
}

async function heartbeat() {
    if (!navigator.onLine) {
        setOnline(false);
        return;
    }

    try {
        const response = await fetch('/up', { method: 'GET', cache: 'no-store' });
        setOnline(response.ok);
    } catch (error) {
        setOnline(false);
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('offline', { online: navigator.onLine });
});

window.addEventListener('online', heartbeat);
window.addEventListener('offline', () => setOnline(false));
window.addEventListener('load', () => {
    heartbeat();
    setInterval(heartbeat, HEARTBEAT_INTERVAL_MS);
});

export { heartbeat };
```

- [ ] **Step 2: Importarlo desde `app.js`**

En `resources/js/app.js`, agregar arriba de todo (antes de `import './bootstrap';`):

```js
import './offline-connectivity';
```

- [ ] **Step 3: Verificar que compila**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
npm run build
```
Esperado: build sin errores, `public/build/assets/app-*.js` crece un poco.

- [ ] **Step 4: Verificar a mano en el navegador**

Abrir `http://localpos.test/dashboard`, consola del navegador:

```js
Alpine.store('offline').online
```
Esperado: `true`. Simular offline con las devtools (Network → Offline) y esperar 15s: debe pasar a `false`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/offline-connectivity.js resources/js/app.js
git commit -m "puntoYA: detector de conexion real (heartbeat) para el modo offline"
```

---

### Task 6: Módulo JS de catálogo + borradores + sincronización

**Files:**
- Create: `resources/js/offline-comanda.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: `GET /mesas/catalogo-offline` (Task 3), `POST /mesas/{id}/comanda/sincronizar` (Task 4), `Alpine.store('offline').online` (Task 5), meta `csrf-token` (Task 2).
- Produces: `window.PuntoyaOffline` con `{ loadCatalog(), refreshCatalog(), getDraft(tableId), upsertDraft(tableId, mutator), clearDraft(tableId), loadDrafts(), syncAllDrafts(), uuid() }` — lo consumen los Tasks 7 y 8.

- [ ] **Step 1: Crear el módulo**

`resources/js/offline-comanda.js`:

```js
// Catálogo cacheado + borradores de comanda por mesa mientras no hay
// conexión, y la cola que los sincroniza al volver. Sin librerías nuevas:
// localStorage alcanza para el volumen de datos de un bar. Ver
// docs/superpowers/specs/2026-09-05-modo-offline-comandas-design.md.
const CATALOG_KEY = 'puntoya_offline_catalog';
const DRAFTS_KEY = 'puntoya_offline_drafts';
const CATALOG_REFRESH_MS = 5 * 60 * 1000;

function uuid() {
    return crypto.randomUUID();
}

function readJSON(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        return fallback;
    }
}

function writeJSON(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        // localStorage lleno o no disponible (modo incógnito): se sigue
        // funcionando en línea, solo se pierde el caché offline.
    }
}

function loadCatalog() {
    return readJSON(CATALOG_KEY, null);
}

async function refreshCatalog() {
    try {
        const response = await fetch('/mesas/catalogo-offline', { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            return loadCatalog();
        }

        const catalog = await response.json();
        writeJSON(CATALOG_KEY, catalog);

        return catalog;
    } catch (error) {
        return loadCatalog();
    }
}

function loadDrafts() {
    return readJSON(DRAFTS_KEY, {});
}

function saveDrafts(drafts) {
    writeJSON(DRAFTS_KEY, drafts);
}

function getDraft(tableId) {
    return loadDrafts()[tableId] ?? null;
}

function upsertDraft(tableId, mutator) {
    const drafts = loadDrafts();
    const current = drafts[tableId] ?? {
        table_id: tableId,
        client_order_uuid: null,
        existing_order_id: null,
        people_count: null,
        requested_bill: false,
        items: [],
        created_at: new Date().toISOString(),
    };

    drafts[tableId] = mutator(current);
    saveDrafts(drafts);

    return drafts[tableId];
}

function clearDraft(tableId) {
    const drafts = loadDrafts();
    delete drafts[tableId];
    saveDrafts(drafts);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function syncDraft(tableId, draft) {
    const response = await fetch(`/mesas/${tableId}/comanda/sincronizar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            client_order_uuid: draft.client_order_uuid,
            existing_order_id: draft.existing_order_id,
            people_count: draft.people_count,
            requested_bill: draft.requested_bill,
            items: draft.items,
        }),
    });

    if (!response.ok) {
        throw new Error(`sync_failed_${response.status}`);
    }

    return response.json();
}

async function syncAllDrafts() {
    const drafts = loadDrafts();
    const results = [];

    for (const [tableId, draft] of Object.entries(drafts)) {
        if (draft.items.length === 0 && !draft.requested_bill) {
            clearDraft(tableId);
            continue;
        }

        try {
            const result = await syncDraft(tableId, draft);
            clearDraft(tableId);
            results.push({ tableId, ok: true, order: result.order });
        } catch (error) {
            results.push({ tableId, ok: false, error: error.message });
        }
    }

    window.dispatchEvent(new CustomEvent('puntoya:drafts-synced', { detail: results }));

    return results;
}

document.addEventListener('alpine:init', () => {
    refreshCatalog();
    setInterval(refreshCatalog, CATALOG_REFRESH_MS);
});

let wasOffline = !navigator.onLine;

setInterval(() => {
    const online = window.Alpine?.store('offline')?.online ?? navigator.onLine;

    if (online && wasOffline) {
        syncAllDrafts();
    }

    wasOffline = !online;
}, 5000);

window.PuntoyaOffline = {
    uuid,
    loadCatalog,
    refreshCatalog,
    loadDrafts,
    getDraft,
    upsertDraft,
    clearDraft,
    syncAllDrafts,
};
```

- [ ] **Step 2: Importarlo desde `app.js`**

En `resources/js/app.js`, junto al import del Task 5:

```js
import './offline-comanda';
```

- [ ] **Step 3: Verificar que compila**

```bash
npm run build
```

- [ ] **Step 4: Verificar a mano en el navegador**

Logueado en `http://localpos.test/mesas`, en la consola:

```js
await PuntoyaOffline.refreshCatalog()
PuntoyaOffline.loadCatalog()   // debe traer productos reales del negocio
PuntoyaOffline.upsertDraft(1, d => ({ ...d, items: [...d.items, { client_item_uuid: 'x', product_id: 1, quantity: 1, modifiers: [] }] }))
PuntoyaOffline.loadDrafts()    // debe tener la mesa 1 con un item
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/offline-comanda.js resources/js/app.js
git commit -m "puntoYA: cache de catalogo, borradores por mesa y cola de sincronizacion"
```

---

### Task 7: Panel offline en `mesas/comanda`

**Files:**
- Modify: `resources/views/components/mesas/⚡comanda.blade.php`

**Interfaces:**
- Consumes: `window.PuntoyaOffline` (Task 6), `Alpine.store('offline').online` (Task 5).

- [ ] **Step 1a: Envolver la apertura del panel en vivo**

En `resources/views/components/mesas/⚡comanda.blade.php`, reemplazar (línea 356-357):

```blade
<div class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-900 lg:h-[80vh] lg:flex-row">
    <div class="flex flex-col gap-3 border-b border-gray-200 bg-white/50 p-4 lg:w-56 lg:shrink-0 lg:overflow-y-auto lg:border-b-0 lg:border-r">
```

por:

```blade
<div x-data="offlineComanda({{ $table->id }}, {{ $order?->id ?? 'null' }})" wire:ignore.self class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-900 lg:h-[80vh] lg:flex-row">
    <template x-if="$store.offline.online">
    <div class="flex flex-1 flex-col lg:flex-row">
    <div class="flex flex-col gap-3 border-b border-gray-200 bg-white/50 p-4 lg:w-56 lg:shrink-0 lg:overflow-y-auto lg:border-b-0 lg:border-r">
```

No se toca ninguna otra línea del panel en vivo (desde el segundo `<div>` de arriba hasta la línea 479 `</div>` que cierra el panel "Comanda" quedan exactamente como están hoy).

- [ ] **Step 1b: Cerrar el panel en vivo y agregar el panel offline**

Reemplazar (líneas 475-481, el botón "Cobrar mesa", el cierre del panel "Comanda" y el comentario del primer modal):

```blade
            <button wire:click="openCheckout" class="mt-4 w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold hover:bg-violet-700 text-white">
                Cobrar mesa
            </button>
        @endif
    </div>

    {{-- Modal de modificadores --}}
```

por:

```blade
            <button wire:click="openCheckout" class="mt-4 w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold hover:bg-violet-700 text-white">
                Cobrar mesa
            </button>
        @endif
    </div>
    </div>
    </template>

    <template x-if="!$store.offline.online">
        <div class="flex flex-1 flex-col p-4">
            <div class="mb-4 rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-700">
                Sin conexión — este pedido se manda solo cuando vuelva internet. Todavía no tiene folio ni se mandó a cocina/barra.
            </div>

            <div class="mb-4 grid grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                <template x-for="product in catalogProducts" :key="product.id">
                    <button type="button" @click="addOfflineItem(product)" class="flex h-28 flex-col justify-between rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-violet-500">
                        <span class="text-sm font-medium" x-text="product.name"></span>
                        <span class="text-violet-600" x-text="'$' + product.price.toFixed(2)"></span>
                    </button>
                </template>
            </div>

            <div class="flex-1 space-y-2 overflow-y-auto border-t border-gray-200 pt-3">
                <template x-if="draftItems.length === 0">
                    <p class="text-center text-sm text-gray-400">Agrega productos a la comanda offline.</p>
                </template>
                <template x-for="item in draftItems" :key="item.client_item_uuid">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-3 text-sm">
                        <div>
                            <div class="font-medium" x-text="item.quantity + ' × ' + item.name"></div>
                            <div class="text-xs text-amber-600">Sin enviar (offline)</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium" x-text="'$' + (item.unit_price * item.quantity).toFixed(2)"></span>
                            <button type="button" @click="removeOfflineItem(item.client_item_uuid)" class="text-red-600 hover:text-red-700">&times;</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-4 space-y-2 border-t border-gray-200 pt-3">
                <div class="flex justify-between text-base font-semibold">
                    <span>Total preliminar</span>
                    <span x-text="'$' + draftTotal.toFixed(2)"></span>
                </div>
                <p class="text-xs text-gray-400">Cuenta preliminar — sin folio, pendiente de conexión.</p>
                <button type="button" @click="requestOfflineBill" :disabled="draftItems.length === 0" class="w-full rounded-lg border border-gray-300 py-2 text-sm text-gray-600 hover:bg-white disabled:opacity-50">
                    Pedir la cuenta (preliminar)
                </button>
            </div>
        </div>
    </template>

    {{-- Modal de modificadores --}}
```

El resto del archivo (modales de modificadores, cobro y venta completada, y el `</div>` final que cierra la raíz) queda exactamente igual — esos modales son overlays de posición fija que no dependen de si hay conexión, así que quedan fuera de los dos `<template>` sin tocarlos.

- [ ] **Step 2: Agregar el componente Alpine `offlineComanda`**

Al final de `resources/js/offline-comanda.js` (antes de `window.PuntoyaOffline = {...}`), agregar:

```js
document.addEventListener('alpine:init', () => {
    window.Alpine.data('offlineComanda', (tableId, existingOrderId) => ({
        catalogProducts: [],
        draftItems: [],
        draftTotal: 0,

        init() {
            this.loadCatalogProducts();
            this.loadDraft();

            window.addEventListener('puntoya:drafts-synced', () => this.loadDraft());
        },

        loadCatalogProducts() {
            const catalog = loadCatalog();
            this.catalogProducts = catalog?.products ?? [];
        },

        loadDraft() {
            const draft = getDraft(tableId);
            this.draftItems = draft?.items ?? [];
            this.recalculateTotal();
        },

        recalculateTotal() {
            this.draftTotal = this.draftItems.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
        },

        addOfflineItem(product) {
            const draft = upsertDraft(tableId, (current) => ({
                ...current,
                existing_order_id: existingOrderId,
                items: [
                    ...current.items,
                    {
                        client_item_uuid: uuid(),
                        product_id: product.id,
                        name: product.name,
                        unit_price: product.price,
                        quantity: 1,
                        modifiers: [],
                    },
                ],
            }));

            this.draftItems = draft.items;
            this.recalculateTotal();
        },

        removeOfflineItem(clientItemUuid) {
            const draft = upsertDraft(tableId, (current) => ({
                ...current,
                items: current.items.filter((item) => item.client_item_uuid !== clientItemUuid),
            }));

            this.draftItems = draft.items;
            this.recalculateTotal();
        },

        requestOfflineBill() {
            upsertDraft(tableId, (current) => ({ ...current, requested_bill: true }));
        },
    }));
});
```

Nota: `loadCatalog`, `getDraft`, `upsertDraft`, `uuid` ya están definidas arriba en el mismo archivo (no hace falta importarlas, son del mismo módulo).

- [ ] **Step 3: Verificar que compila**

```bash
npm run build
```

- [ ] **Step 4: Verificar a mano en el navegador**

En `http://localpos.test/mesas/{id}/comanda` con una mesa real:
1. DevTools → Network → Offline.
2. Esperar el heartbeat (≤15s) o forzar `Alpine.store('offline').online = false` en consola.
3. Confirmar que aparece el panel offline, se pueden agregar productos del catálogo cacheado, y "Pedir la cuenta" marca el total preliminar.
4. DevTools → Network → Online otra vez, esperar el heartbeat, y confirmar (Network tab) que sale el `POST /mesas/{id}/comanda/sincronizar` y que al recargar la página el pedido aparece con folio real dentro del flujo normal (Livewire).

- [ ] **Step 5: Correr toda la suite del servidor (esto no toca PHP, pero confirma que no se rompió nada)**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=ComandaFlowTest
```
Esperado: PASS (el flujo en línea original queda intacto porque solo se activa dentro del `template x-if="$store.offline.online"`).

- [ ] **Step 6: Commit**

```bash
git add "resources/views/components/mesas/⚡comanda.blade.php" resources/js/offline-comanda.js
git commit -m "puntoYA: panel offline para armar comandas sin conexion en mesas/comanda"
```

---

### Task 8: Indicador de "pendiente de sincronizar" en el mapa de mesas

**Files:**
- Modify: `resources/views/components/mesas/⚡mapa.blade.php`

**Interfaces:**
- Consumes: `window.PuntoyaOffline.loadDrafts()` (Task 6).

- [ ] **Step 1: Marcar cada tarjeta de mesa con su id**

En `resources/views/components/mesas/⚡mapa.blade.php:80`, agregar `data-table-id="{{ $table->id }}"` al `<div class="rounded-xl border {{ $colors[...] }} p-4">`:

```blade
                        <div data-table-id="{{ $table->id }}" class="relative rounded-xl border {{ $colors[$table->status->value] }} p-4">
```

- [ ] **Step 2: Agregar el badge y el script que lo actualiza**

Justo antes de `<a href="{{ route('mesas.comanda', $table) }}" ...>` (dentro del mismo div, línea 81), agregar:

```blade
                            <span x-data="{ pending: false }"
                                x-init="pending = !!PuntoyaOffline.getDraft({{ $table->id }})?.items?.length;
                                    window.addEventListener('puntoya:drafts-synced', () => pending = !!PuntoyaOffline.getDraft({{ $table->id }})?.items?.length);
                                    setInterval(() => pending = !!PuntoyaOffline.getDraft({{ $table->id }})?.items?.length, 3000)"
                                x-show="pending" x-cloak
                                class="absolute right-2 top-2 rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-semibold text-white">
                                Pendiente
                            </span>
```

- [ ] **Step 2: Verificar que compila**

No hay build de JS involucrado en este paso (es solo Blade), pero confirmar que la vista renderiza:

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=ComandaFlowTest
```
Esperado: PASS (no debería verse afectado por este cambio puramente visual).

- [ ] **Step 3: Verificar a mano en el navegador**

Con un borrador offline pendiente en `localStorage` (dejado del Task 7), abrir `/mesas` y confirmar que la mesa correspondiente muestra el badge "Pendiente". Sincronizar (volver online) y confirmar que el badge desaparece solo.

- [ ] **Step 4: Commit**

```bash
git add "resources/views/components/mesas/⚡mapa.blade.php"
git commit -m "puntoYA: indicador de comanda pendiente de sincronizar en el mapa de mesas"
```

---

### Task 9: Deshabilitar las acciones de Caja sin conexión

**Files:**
- Modify: `resources/views/components/caja/⚡apertura.blade.php:91`
- Modify: `resources/views/components/caja/⚡movimientos.blade.php:91`
- Modify: `resources/views/components/caja/⚡cierre.blade.php:240`

**Interfaces:**
- Consumes: `Alpine.store('offline').online` (Task 5).

- [ ] **Step 1: Apertura**

En `resources/views/components/caja/⚡apertura.blade.php:91`, el botón de submit pasa de:

```blade
                <button type="submit" class="w-full rounded-lg bg-violet-600 px-4 py-2 font-medium hover:bg-violet-700 text-white">
```

a:

```blade
                <button type="submit" x-data :disabled="!$store.offline.online" class="w-full rounded-lg bg-violet-600 px-4 py-2 font-medium hover:bg-violet-700 text-white disabled:opacity-50" :title="$store.offline.online ? '' : 'Necesita conexión'">
```

- [ ] **Step 2: Movimientos**

En `resources/views/components/caja/⚡movimientos.blade.php:91`, el botón "Registrar" pasa de:

```blade
                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Registrar</button>
```

a:

```blade
                    <button type="submit" x-data :disabled="!$store.offline.online" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white disabled:opacity-50" :title="$store.offline.online ? '' : 'Necesita conexión'">Registrar</button>
```

- [ ] **Step 3: Cierre**

En `resources/views/components/caja/⚡cierre.blade.php:240`, el botón `close` pasa de:

```blade
                    <button wire:click="close" class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold hover:bg-emerald-500 text-white">
```

a:

```blade
                    <button wire:click="close" x-data :disabled="!$store.offline.online" class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold hover:bg-emerald-500 text-white disabled:opacity-50" :title="$store.offline.online ? '' : 'Necesita conexión'">
```

- [ ] **Step 4: Correr los tests de caja**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test --filter=CashRegister
```
Esperado: PASS (los tests de Livewire llaman los métodos directo, no dependen de que el botón esté habilitado en el DOM).

- [ ] **Step 5: Verificar a mano en el navegador**

En `/caja/apertura`, `/caja/movimientos` y `/caja/cierre`, simular offline (DevTools → Network → Offline, esperar el heartbeat) y confirmar que el botón principal de cada pantalla queda deshabilitado con el tooltip "Necesita conexión", y vuelve a habilitarse al reconectar.

- [ ] **Step 6: Commit**

```bash
git add "resources/views/components/caja/⚡apertura.blade.php" "resources/views/components/caja/⚡movimientos.blade.php" "resources/views/components/caja/⚡cierre.blade.php"
git commit -m "puntoYA: deshabilita las acciones de caja cuando no hay conexion"
```

---

### Task 10: QA manual end-to-end y suite completa

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa del servidor**

```bash
export PATH="/c/laragon/bin/php/php-8.4.25-nts-Win32-vs17-x64:$PATH"
php artisan test
```
Esperado: todos los tests en verde (los ~210 existentes + los agregados en Tasks 1, 3 y 4).

- [ ] **Step 2: Build de producción**

```bash
npm run build
```
Esperado: sin errores.

- [ ] **Step 3: QA manual con el navegador (Playwright o a mano), guion completo**

1. Login como cajero/mesero con `ventas.crear`, abrir terminal y caja.
2. Ir a `/mesas`, entrar a una mesa vacía.
3. DevTools → Network → Offline (o `context().setOffline(true)` si es con Playwright, mismo patrón que se usó para probar `MirrorOfflineTest` en esta sesión).
4. Confirmar: aparece el panel offline, se pueden agregar 2-3 productos, "Pedir la cuenta" muestra el total preliminar correcto.
5. Ir a `/caja/movimientos`: el botón "Registrar" está deshabilitado.
6. Volver online. Esperar ≤15s (heartbeat) + la sincronización automática.
7. Confirmar en `/mesas` que la mesa ya no tiene el badge "Pendiente".
8. Entrar a la comanda de esa mesa: los productos agregados offline aparecen como "Enviado", con folio real (`COMANDA-...`), y se puede cobrar normalmente.
9. Revisar `storage/logs/laravel.log`: no debe haber excepciones nuevas.

- [ ] **Step 4: Actualizar la spec con el resultado del QA**

Agregar al final de `docs/superpowers/specs/2026-09-05-modo-offline-comandas-design.md` una sección corta `## QA manual (fecha)` con el resultado del guion del Step 3 (qué se probó, con qué usuario/rol, y cualquier ajuste que haya hecho falta).

- [ ] **Step 5: Commit final**

```bash
git add docs/superpowers/specs/2026-09-05-modo-offline-comandas-design.md
git commit -m "puntoYA: QA manual del modo offline de comandas"
```
