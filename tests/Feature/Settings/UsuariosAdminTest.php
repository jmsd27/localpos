<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('un cajero no puede ver el listado de usuarios', function () {
    loginAsRole(RoleName::Cajero->value);

    $this->get(route('admin.usuarios'))->assertForbidden();
});

test('un administrador puede crear un usuario con rol y ese usuario puede iniciar sesion', function () {
    $admin = loginAsRole(RoleName::Administrador->value);
    $branch = Branch::where('business_id', $admin->businessId())->firstOrFail();

    Livewire::test('admin.usuarios.index')
        ->call('create')
        ->set('name', 'Carlos Cajero')
        ->set('email', 'carlos@localpos.local')
        ->set('branch_id', $branch->id)
        ->set('role', RoleName::Cajero->value)
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'carlos@localpos.local')->firstOrFail();
    expect($newUser->hasRole('cajero'))->toBeTrue();
    expect($newUser->is_active)->toBeTrue();

    Livewire::test('auth.login')
        ->set('email', 'carlos@localpos.local')
        ->set('password', 'password123')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($newUser);
});

test('no se puede asignar el rol super-admin desde la pantalla de usuarios', function () {
    $admin = loginAsRole(RoleName::Administrador->value);
    $branch = Branch::where('business_id', $admin->businessId())->firstOrFail();

    Livewire::test('admin.usuarios.index')
        ->call('create')
        ->set('name', 'Intento')
        ->set('email', 'intento@localpos.local')
        ->set('branch_id', $branch->id)
        ->set('role', RoleName::SuperAdmin->value)
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['role']);
});

test('editar un usuario sin llenar la contrasena conserva la contrasena anterior', function () {
    $admin = loginAsRole(RoleName::Administrador->value);
    $branch = Branch::where('business_id', $admin->businessId())->firstOrFail();

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole(RoleName::Mesero->value);
    $originalHash = $user->password;

    Livewire::test('admin.usuarios.index')
        ->call('edit', $user->id)
        ->set('name', 'Nombre Actualizado')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Nombre Actualizado');
    expect($user->fresh()->password)->toBe($originalHash);
});

test('desactivar un usuario le impide iniciar sesion aunque la contrasena sea correcta', function () {
    $admin = loginAsRole(RoleName::Administrador->value);
    $branch = Branch::where('business_id', $admin->businessId())->firstOrFail();

    $user = User::factory()->create(['branch_id' => $branch->id, 'password' => Hash::make('password123')]);
    $user->assignRole(RoleName::Mesero->value);

    Livewire::test('admin.usuarios.index')->call('toggleActive', $user->id);

    expect($user->fresh()->is_active)->toBeFalse();

    auth()->logout();

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password123')
        ->call('login')
        ->assertSee('desactivada');

    $this->assertGuest();
});

test('un administrador no puede desactivar su propia cuenta', function () {
    $admin = loginAsRole(RoleName::Administrador->value);

    Livewire::test('admin.usuarios.index')->call('toggleActive', $admin->id);

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('el correo debe ser unico entre usuarios', function () {
    $admin = loginAsRole(RoleName::Administrador->value);
    $branch = Branch::where('business_id', $admin->businessId())->firstOrFail();

    $existing = User::factory()->create(['branch_id' => $branch->id, 'email' => 'ocupado@localpos.local']);

    Livewire::test('admin.usuarios.index')
        ->call('create')
        ->set('name', 'Otro')
        ->set('email', 'ocupado@localpos.local')
        ->set('branch_id', $branch->id)
        ->set('role', RoleName::Mesero->value)
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['email']);
});
