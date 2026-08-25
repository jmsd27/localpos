<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Business;
use Livewire\Livewire;

test('la pantalla de auditoria filtra por accion', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    AuditLog::create(['business_id' => $user->businessId(), 'user_id' => $user->id, 'action' => 'venta.crear', 'created_at' => now()]);
    AuditLog::create(['business_id' => $user->businessId(), 'user_id' => $user->id, 'action' => 'caja.abrir', 'created_at' => now()]);

    Livewire::test('admin.auditoria.index')
        ->set('action', 'venta')
        ->assertSee('venta.crear')
        ->assertDontSee('caja.abrir');
});

test('la pantalla de auditoria solo muestra registros del propio negocio', function () {
    $user = loginAsRole(RoleName::Administrador->value);

    $otherBusiness = Business::factory()->create();

    AuditLog::create(['business_id' => $user->businessId(), 'user_id' => $user->id, 'action' => 'venta.crear', 'created_at' => now()]);
    AuditLog::create(['business_id' => $otherBusiness->id, 'user_id' => null, 'action' => 'compras.crear', 'created_at' => now()]);

    Livewire::test('admin.auditoria.index')
        ->assertSee('venta.crear')
        ->assertDontSee('compras.crear');
});
