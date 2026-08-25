<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Crea un negocio + sucursal + usuario con el rol dado y autentica al usuario.
 * Siembra roles/permisos primero (RefreshDatabase resetea la BD en cada test).
 */
function loginAsRole(string $role): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->seed(PermissionSeeder::class);
    test()->seed(RoleSeeder::class);

    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole($role);

    test()->actingAs($user);

    return $user;
}
