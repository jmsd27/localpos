<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

test('cada rol definido en el spec existe', function () {
    foreach (RoleName::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }
});

test('el rol cajero tiene el subconjunto de permisos esperado', function () {
    $cajero = Role::where('name', RoleName::Cajero->value)->first();

    expect($cajero->hasPermissionTo('ventas.crear'))->toBeTrue();
    expect($cajero->hasPermissionTo('caja.abrir'))->toBeTrue();
    expect($cajero->hasPermissionTo('usuarios.eliminar'))->toBeFalse();
});

test('un usuario cajero no pasa un gate reservado a usuarios.eliminar', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Cajero->value);

    expect($user->can('usuarios.eliminar'))->toBeFalse();
});

test('el super-admin pasa cualquier gate vía el bypass de Gate::before', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::SuperAdmin->value);

    expect($user->can('usuarios.eliminar'))->toBeTrue();
    expect($user->can('cualquier.permiso.inexistente'))->toBeTrue();
});
