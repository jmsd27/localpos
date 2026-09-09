<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\TableStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableArea;
use App\Models\User;
use App\Services\ManagerPinService;
use Livewire\Livewire;

/**
 * Crea una mesa con comanda pendiente y devuelve [component, table, order, operador].
 */
function mesaConComanda(string $operatorRole = 'mesero'): array
{
    [$user] = posContext($operatorRole);

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);
    $product = Product::factory()->create(['business_id' => $user->businessId(), 'price' => 120, 'tax_rate' => 0]);

    $component = Livewire::test('mesas.comanda', ['table' => $table])
        ->call('addProduct', $product->id)
        ->call('sendComanda');

    $order = Order::where('table_id', $table->id)->firstOrFail();

    return [$component, $table, $order, $user];
}

function administradorConClave(int $branchId, string $pin = '4321'): User
{
    $admin = User::factory()->create(['branch_id' => $branchId, 'pin_hash' => $pin]);
    $admin->assignRole(RoleName::Administrador->value);

    return $admin;
}

test('el administrador carga su clave de cancelacion desde Usuarios y queda hasheada', function () {
    $admin = loginAsRole(RoleName::Administrador->value);
    $branchId = $admin->branch_id;

    Livewire::test('admin.usuarios.index')
        ->call('create')
        ->set('name', 'Gerente Turno')
        ->set('email', 'gerente@bar.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('cancel_pin', '9090')
        ->set('branch_id', $branchId)
        ->set('role', RoleName::Administrador->value)
        ->call('save')
        ->assertHasNoErrors();

    $gerente = User::where('email', 'gerente@bar.test')->firstOrFail();

    expect($gerente->pin_hash)->not->toBeNull()
        ->and($gerente->pin_hash)->not->toBe('9090')
        ->and(app(ManagerPinService::class)->resolveAuthorizer($admin->businessId(), '9090')?->id)->toBe($gerente->id);
});

test('con la clave correcta se cancela la cuenta, se libera la mesa y queda auditado', function () {
    [$component, $table, $order, $operador] = mesaConComanda();
    $admin = administradorConClave($operador->branch_id, '4321');

    $component
        ->call('openCancelAccount')
        ->set('cancelReason', 'El cliente se retiró')
        ->set('cancelPin', '4321')
        ->call('confirmCancelAccount')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($table->fresh()->status)->toBe(TableStatus::Available);

    $audit = AuditLog::where('action', 'venta.cancelar_cuenta')->latest('id')->firstOrFail();
    expect($audit->after['authorized_by'])->toBe($admin->id)
        ->and($audit->after['operator_id'])->toBe($operador->id)
        ->and($audit->after['reason'])->toBe('El cliente se retiró');
});

test('una clave incorrecta no cancela nada', function () {
    [$component, $table, $order, $operador] = mesaConComanda();
    administradorConClave($operador->branch_id, '4321');

    $component
        ->call('openCancelAccount')
        ->set('cancelReason', 'prueba')
        ->set('cancelPin', '0000')
        ->call('confirmCancelAccount');

    $component->assertSet('cancelAccountError', 'Clave de administrador incorrecta.');
    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($table->fresh()->status)->toBe(TableStatus::Occupied);
});

test('sin motivo no se puede cancelar', function () {
    [$component, , $order, $operador] = mesaConComanda();
    administradorConClave($operador->branch_id, '4321');

    $component
        ->call('openCancelAccount')
        ->set('cancelPin', '4321')
        ->call('confirmCancelAccount')
        ->assertSet('cancelAccountError', 'Escribí el motivo de la cancelación.');

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('si ningun administrador tiene clave cargada, avisa que hay que cargarla', function () {
    [$component, , $order] = mesaConComanda();

    $component
        ->call('openCancelAccount')
        ->set('cancelReason', 'prueba')
        ->set('cancelPin', '1234')
        ->call('confirmCancelAccount');

    $component->assertSet('cancelAccountError', 'Ningún administrador tiene clave de autorización cargada. Cargala en Administración → Usuarios.');
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

test('un usuario sin el permiso ventas.cancelar_cuenta no puede abrir el modal', function () {
    [$user] = posContext('mesero');
    $user->removeRole(RoleName::Mesero->value);
    $user->givePermissionTo('ventas.crear', 'ventas.ver');

    $area = TableArea::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id]);
    $table = Table::factory()->create(['business_id' => $user->businessId(), 'branch_id' => $user->branch_id, 'table_area_id' => $area->id]);

    Livewire::test('mesas.comanda', ['table' => $table])
        ->call('openCancelAccount')
        ->assertForbidden();
});

test('la clave de cancelacion debe tener al menos 4 caracteres', function () {
    $admin = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.usuarios.index')
        ->call('create')
        ->set('name', 'X')
        ->set('email', 'x@bar.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('cancel_pin', '12')
        ->set('branch_id', $admin->branch_id)
        ->set('role', RoleName::Cajero->value)
        ->call('save')
        ->assertHasErrors(['cancel_pin']);
});
