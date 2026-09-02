<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SyncIdMap;
use App\Models\SyncOutboxEntry;
use App\Models\User;
use App\Services\SyncIngestionService;
use App\Services\SyncModelResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Arma una entrada de lote tal como la enviaría SyncPushService, usando el
 * mismo snapshot() real que produce el observer en el lado "source".
 */
function syncEntry(object $model, string $type, string $operation = 'created', ?int $localId = null, ?int $entryId = null): array
{
    $resolver = app(SyncModelResolver::class);

    return [
        'id' => $entryId ?? fake()->unique()->numberBetween(1, 1_000_000),
        'model_type' => $type,
        'model_id' => $localId ?? $model->getKey(),
        'operation' => $operation,
        'payload' => $operation === 'deleted'
            ? null
            : $resolver->snapshot($model, config("sync.models.{$type}")),
        'occurred_at' => now()->toIso8601String(),
    ];
}

/** Simula sync:provision-branch: crea el negocio/sucursal placeholder en la nube y los mapea. */
function provisionCloudBranch(string $branchCode, int $localBusinessId, int $localBranchId): array
{
    // El código de sucursal es único en el esquema; en producción "source" y
    // "mirror" son bases separadas, pero aquí comparten la BD de test, así que
    // la fila-espejo de la sucursal lleva otro code (la ingesta solo usa el
    // branch_code del parámetro + sync_id_map, nunca Branch.code).
    $cloudBusiness = Business::factory()->create(['name' => 'Negocio Nube']);
    $cloudBranch = Branch::factory()->for($cloudBusiness)->create();

    mapCloudId($branchCode, 'business', $localBusinessId, $cloudBusiness->id);
    mapCloudId($branchCode, 'branch', $localBranchId, $cloudBranch->id);

    return [$cloudBusiness, $cloudBranch];
}

/** Provisiona negocio + sucursal + usuario de una orden local en la nube. */
function provisionOrderDeps(Order $localOrder, string $branchCode): array
{
    [$cloudBusiness, $cloudBranch] = provisionCloudBranch($branchCode, $localOrder->business_id, $localOrder->branch_id);

    if ($localOrder->user_id) {
        mapCloudId($branchCode, 'user', $localOrder->user_id, User::factory()->create(['branch_id' => $cloudBranch->id])->id);
    }

    return [$cloudBusiness, $cloudBranch];
}

function mapCloudId(string $branchCode, string $modelType, int $localId, int $cloudId): void
{
    SyncIdMap::create([
        'branch_code' => $branchCode,
        'model_type' => $modelType,
        'local_id' => $localId,
        'cloud_id' => $cloudId,
    ]);
}

function ingest(string $branchCode, array $entries): array
{
    return app(SyncIngestionService::class)->applyBatch($branchCode, $entries);
}

/*
|--------------------------------------------------------------------------
| Captura local: observer + outbox
|--------------------------------------------------------------------------
*/

test('crear una orden encola una entrada de outbox con la sucursal resuelta', function () {
    $branch = Branch::factory()->create();
    $order = Order::factory()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);

    $entry = SyncOutboxEntry::where('model_type', 'order')->where('model_id', $order->id)->sole();

    expect($entry->operation)->toBe('created')
        ->and($entry->branch_id)->toBe($branch->id)
        ->and($entry->business_id)->toBe($branch->business_id)
        ->and($entry->payload['folio'])->toBe($order->folio)
        ->and($entry->synced_at)->toBeNull();
});

test('los modelos sin branch_id propio resuelven la sucursal caminando la relacion', function () {
    $branch = Branch::factory()->create();
    $order = Order::factory()->create(['business_id' => $branch->business_id, 'branch_id' => $branch->id]);
    $product = Product::factory()->create(['business_id' => $branch->business_id]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => $product->name,
        'quantity' => 2,
        'unit_price' => 50,
        'subtotal' => 100,
    ]);
    $payment = Payment::create([
        'order_id' => $order->id,
        'method' => 'efectivo',
        'amount' => 100,
        'received_amount' => 100,
        'change_amount' => 0,
    ]);

    $itemEntry = SyncOutboxEntry::where('model_type', 'order_item')->where('model_id', $item->id)->sole();
    $paymentEntry = SyncOutboxEntry::where('model_type', 'payment')->where('model_id', $payment->id)->sole();

    expect($itemEntry->branch_id)->toBe($branch->id)
        ->and($itemEntry->business_id)->toBe($branch->business_id)
        ->and($paymentEntry->branch_id)->toBe($branch->id);
});

test('el snapshot de un usuario reemplaza los campos sensibles por valores aleatorios', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'password' => 'hash-real-conocido',
        'pin_hash' => 'pin-real-conocido',
    ]);

    $entry = SyncOutboxEntry::where('model_type', 'user')->where('model_id', $user->id)->sole();

    expect($entry->payload)->toHaveKey('password')
        ->and($entry->payload)->toHaveKey('pin_hash')
        ->and($entry->payload['password'])->not->toBe($user->getAttributes()['password'])
        ->and($entry->payload['pin_hash'])->not->toBe($user->getAttributes()['pin_hash'])
        ->and($entry->payload['email'])->toBe($user->email);
});

test('un delete encola una entrada sin payload', function () {
    $product = Product::factory()->create();
    $product->delete();

    $entry = SyncOutboxEntry::where('model_type', 'product')
        ->where('model_id', $product->id)
        ->where('operation', 'deleted')
        ->sole();

    expect($entry->payload)->toBeNull();
});

test('el observer no registra nada cuando el rol no es source', function () {
    config()->set('sync.role', 'mirror');

    Product::factory()->create();

    expect(SyncOutboxEntry::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Ingesta en la nube: identidad cruzada, idempotencia, orden
|--------------------------------------------------------------------------
*/

test('una orden ingerida crea una fila nueva con las FK reescritas a IDs de la nube', function () {
    config()->set('sync.role', 'mirror');

    $localBranch = Branch::factory()->create();
    $localOrder = Order::factory()->create([
        'business_id' => $localBranch->business_id,
        'branch_id' => $localBranch->id,
        'total' => 250,
    ]);

    [$cloudBusiness, $cloudBranch] = provisionOrderDeps($localOrder, $localBranch->code);

    $result = ingest($localBranch->code, [syncEntry($localOrder, 'order', localId: $localOrder->id)]);

    expect($result['accepted'])->toHaveCount(1)
        ->and($result['rejected'])->toBeEmpty();

    $cloudId = SyncIdMap::where('branch_code', $localBranch->code)
        ->where('model_type', 'order')->where('local_id', $localOrder->id)->value('cloud_id');

    $cloudOrder = Order::findOrFail($cloudId);
    expect($cloudOrder->is($localOrder))->toBeFalse()
        ->and($cloudOrder->business_id)->toBe($cloudBusiness->id)
        ->and($cloudOrder->branch_id)->toBe($cloudBranch->id)
        ->and((float) $cloudOrder->total)->toBe(250.0)
        ->and($cloudOrder->folio)->toBe($localOrder->folio);
});

test('reaplicar el mismo lote es idempotente (no duplica filas)', function () {
    config()->set('sync.role', 'mirror');

    $localBranch = Branch::factory()->create();
    $localOrder = Order::factory()->create(['business_id' => $localBranch->business_id, 'branch_id' => $localBranch->id]);
    provisionOrderDeps($localOrder, $localBranch->code);

    $entry = syncEntry($localOrder, 'order', localId: $localOrder->id, entryId: 42);

    $ordersBefore = Order::count();
    ingest($localBranch->code, [$entry]);
    ingest($localBranch->code, [$entry]);

    expect(Order::count())->toBe($ordersBefore + 1)
        ->and(SyncIdMap::where('model_type', 'order')->where('local_id', $localOrder->id)->count())->toBe(1);
});

test('un update sobre una fila ya mapeada la actualiza en vez de crear otra', function () {
    config()->set('sync.role', 'mirror');

    $localBranch = Branch::factory()->create();
    $localOrder = Order::factory()->create(['business_id' => $localBranch->business_id, 'branch_id' => $localBranch->id, 'total' => 100]);
    provisionOrderDeps($localOrder, $localBranch->code);

    ingest($localBranch->code, [syncEntry($localOrder, 'order', localId: $localOrder->id)]);

    $localOrder->update(['total' => 999, 'status' => 'cancelled']);
    $result = ingest($localBranch->code, [syncEntry($localOrder, 'order', 'updated', localId: $localOrder->id)]);

    $cloudId = SyncIdMap::where('model_type', 'order')->where('local_id', $localOrder->id)->value('cloud_id');

    $cloudRow = DB::table('orders')->where('id', $cloudId)->first();

    expect($result['accepted'])->toHaveCount(1)
        ->and((float) $cloudRow->total)->toBe(999.0)
        ->and($cloudRow->status)->toBe('cancelled')
        ->and(Order::where('id', $cloudId)->count())->toBe(1);
});

test('una entrada hija cuyo padre no ha llegado se difiere sin romper el resto del lote', function () {
    config()->set('sync.role', 'mirror');

    $localBranch = Branch::factory()->create();
    $localOrder = Order::factory()->create(['business_id' => $localBranch->business_id, 'branch_id' => $localBranch->id]);
    $product = Product::factory()->create(['business_id' => $localBranch->business_id]);
    $localItem = OrderItem::create([
        'order_id' => $localOrder->id, 'product_id' => $product->id, 'name' => $product->name,
        'quantity' => 1, 'unit_price' => 10, 'subtotal' => 10,
    ]);

    [$cloudBusiness] = provisionOrderDeps($localOrder, $localBranch->code);
    mapCloudId($localBranch->code, 'product', $product->id, Product::factory()->create(['business_id' => $cloudBusiness->id])->id);

    // El hijo llega ANTES que el padre.
    $result = ingest($localBranch->code, [
        syncEntry($localItem, 'order_item', localId: $localItem->id, entryId: 1),
        syncEntry($localOrder, 'order', localId: $localOrder->id, entryId: 2),
    ]);

    expect($result['accepted'])->toContain(2)
        ->and($result['rejected'])->toHaveCount(1)
        ->and($result['rejected'][0]['id'])->toBe(1);

    // En el siguiente push el hijo ya resuelve a su padre.
    $retry = ingest($localBranch->code, [syncEntry($localItem, 'order_item', localId: $localItem->id, entryId: 1)]);
    expect($retry['accepted'])->toContain(1)
        ->and(SyncIdMap::where('model_type', 'order_item')->where('local_id', $localItem->id)->count())->toBe(1);
});

test('dos sucursales con el mismo local_id no chocan en la nube', function () {
    config()->set('sync.role', 'mirror');

    $branchA = Branch::factory()->create(['code' => 'MTY-01']);
    $branchB = Branch::factory()->create(['code' => 'CDMX-02']);

    // Cada sucursal crea "su" orden #id-coincidente.
    $orderA = Order::factory()->create(['business_id' => $branchA->business_id, 'branch_id' => $branchA->id, 'folio' => 'A-0001']);
    $orderB = Order::factory()->create(['business_id' => $branchB->business_id, 'branch_id' => $branchB->id, 'folio' => 'B-0001']);

    provisionOrderDeps($orderA, 'MTY-01');
    provisionOrderDeps($orderB, 'CDMX-02');

    $sameLocalId = 7;
    ingest('MTY-01', [syncEntry($orderA, 'order', localId: $sameLocalId)]);
    ingest('CDMX-02', [syncEntry($orderB, 'order', localId: $sameLocalId)]);

    $rows = SyncIdMap::where('model_type', 'order')->where('local_id', $sameLocalId)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('cloud_id')->unique())->toHaveCount(2)
        ->and($rows->pluck('branch_code')->sort()->values()->all())->toBe(['CDMX-02', 'MTY-01']);
});

test('un modelo desconocido se rechaza sin abortar el lote', function () {
    config()->set('sync.role', 'mirror');
    $branch = Branch::factory()->create();

    $result = ingest($branch->code, [[
        'id' => 1, 'model_type' => 'inexistente', 'model_id' => 1,
        'operation' => 'created', 'payload' => ['x' => 1], 'occurred_at' => now()->toIso8601String(),
    ]]);

    expect($result['accepted'])->toBeEmpty()
        ->and($result['rejected'])->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Fidelidad de datos en el espejo (reporting / auditoría)
|--------------------------------------------------------------------------
*/

test('la ingesta conserva el created_at original de la fila de origen', function () {
    config()->set('sync.role', 'mirror');

    $localBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $localBranch->id]);
    [$cloudBusiness, $cloudBranch] = provisionCloudBranch($localBranch->code, $localBranch->business_id, $localBranch->id);
    mapCloudId($localBranch->code, 'user', $user->id, User::factory()->create(['branch_id' => $cloudBranch->id])->id);

    $happenedAt = now()->subDays(9)->startOfMinute();
    $localLog = AuditLog::create([
        'user_id' => $user->id,
        'business_id' => $localBranch->business_id,
        'branch_id' => $localBranch->id,
        'action' => 'venta.crear',
        'before' => null,
        'after' => ['total' => 120],
        'created_at' => $happenedAt,
    ]);

    ingest($localBranch->code, [syncEntry($localLog, 'audit_log', localId: $localLog->id)]);

    $cloudId = SyncIdMap::where('model_type', 'audit_log')->where('local_id', $localLog->id)->value('cloud_id');
    $cloudLog = AuditLog::findOrFail($cloudId);

    expect($cloudLog->created_at)->not->toBeNull()
        ->and($cloudLog->created_at->equalTo($happenedAt))->toBeTrue();
});

test('la ingesta conserva los campos JSON sin doble codificar', function () {
    config()->set('sync.role', 'mirror');

    $localBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $localBranch->id]);
    [, $cloudBranch] = provisionCloudBranch($localBranch->code, $localBranch->business_id, $localBranch->id);
    mapCloudId($localBranch->code, 'user', $user->id, User::factory()->create(['branch_id' => $cloudBranch->id])->id);

    $localLog = AuditLog::create([
        'user_id' => $user->id,
        'business_id' => $localBranch->business_id,
        'branch_id' => $localBranch->id,
        'action' => 'producto.editar',
        'before' => ['price' => 10, 'name' => 'Café'],
        'after' => ['price' => 12, 'name' => 'Café'],
        'created_at' => now(),
    ]);

    ingest($localBranch->code, [syncEntry($localLog, 'audit_log', localId: $localLog->id)]);

    $cloudId = SyncIdMap::where('model_type', 'audit_log')->where('local_id', $localLog->id)->value('cloud_id');
    $cloudLog = AuditLog::findOrFail($cloudId);

    expect($cloudLog->before)->toBe(['price' => 10, 'name' => 'Café'])
        ->and($cloudLog->after)->toBe(['price' => 12, 'name' => 'Café']);
});

/*
|--------------------------------------------------------------------------
| Endpoint HTTP + autenticación por token
|--------------------------------------------------------------------------
*/

test('el endpoint de ingesta exige el header X-Sync-Token', function () {
    config()->set('sync.role', 'mirror');

    postJson('/api/sync/ingest', ['branch_code' => 'X', 'entries' => []])
        ->assertStatus(401);
});

test('el endpoint de ingesta rechaza un token que no corresponde a ninguna sucursal', function () {
    config()->set('sync.role', 'mirror');

    postJson('/api/sync/ingest', ['branch_code' => 'X', 'entries' => []], ['X-Sync-Token' => 'token-invalido'])
        ->assertStatus(401);
});

test('con un token válido el endpoint acepta un lote vacío', function () {
    config()->set('sync.role', 'mirror');
    $branch = Branch::factory()->create(['sync_token' => 'token-valido-1234567890']);

    postJson('/api/sync/ingest', ['branch_code' => $branch->code, 'entries' => []], ['X-Sync-Token' => 'token-valido-1234567890'])
        ->assertOk()
        ->assertJson(['accepted' => [], 'rejected' => []]);

    expect($branch->fresh()->last_synced_at)->not->toBeNull();
});

test('una instancia source nunca acepta pushes aunque el token sea válido', function () {
    config()->set('sync.role', 'source');
    $branch = Branch::factory()->create(['sync_token' => 'token-valido-1234567890']);

    postJson('/api/sync/ingest', ['branch_code' => $branch->code, 'entries' => []], ['X-Sync-Token' => 'token-valido-1234567890'])
        ->assertNotFound();
});

test('el endpoint valida la forma de las entradas', function () {
    config()->set('sync.role', 'mirror');
    $branch = Branch::factory()->create(['sync_token' => 'token-valido-1234567890']);

    postJson('/api/sync/ingest', [
        'branch_code' => $branch->code,
        'entries' => [['id' => 'no-es-entero', 'operation' => 'raro']],
    ], ['X-Sync-Token' => 'token-valido-1234567890'])
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Bloqueo de escritura en el espejo (Gate::before)
|--------------------------------------------------------------------------
*/

function userWithRole(string $role): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(PermissionSeeder::class);
    test()->seed(RoleSeeder::class);

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole($role);

    return $user;
}

test('en el espejo un rol con permiso de venta no puede crear ventas pero sí verlas', function () {
    $user = userWithRole(RoleName::Cajero->value);

    config()->set('sync.role', 'source');
    expect(Gate::forUser($user)->allows('ventas.crear'))->toBeTrue();

    config()->set('sync.role', 'mirror');
    expect(Gate::forUser($user)->allows('ventas.crear'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('caja.abrir'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('ventas.ver'))->toBeTrue();
});

test('en el espejo el bypass de super-admin no reactiva la escritura', function () {
    $user = userWithRole(RoleName::SuperAdmin->value);

    config()->set('sync.role', 'mirror');

    expect(Gate::forUser($user)->allows('ventas.crear'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('configuracion.editar'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('reportes.ver'))->toBeTrue();
});
