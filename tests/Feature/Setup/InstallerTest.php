<?php

use App\Models\Business;
use App\Models\User;
use Livewire\Livewire;

test('en una base de datos vacia la raiz redirige a instalacion', function () {
    $this->get('/')->assertRedirect(route('instalacion'));
});

test('en una base de datos ya instalada la raiz redirige al dashboard', function () {
    Business::factory()->create();

    $this->get('/')->assertRedirect(route('dashboard'));
});

test('la pantalla de instalacion no esta disponible si ya existe un negocio', function () {
    Business::factory()->create();

    $this->get(route('instalacion'))->assertNotFound();
});

test('completar la instalacion crea el negocio, la sucursal, los roles y el admin, y deja la sesion iniciada', function () {
    Livewire::test('instalacion.index')
        ->set('business_name', 'Taquería El Buen Sazón')
        ->set('currency', 'MXN')
        ->set('timezone', 'America/Mexico_City')
        ->set('admin_name', 'Dueño')
        ->set('admin_email', 'dueno@taqueria.local')
        ->set('admin_password', 'password123')
        ->set('admin_password_confirmation', 'password123')
        ->call('install')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $business = Business::where('name', 'Taquería El Buen Sazón')->firstOrFail();
    expect($business->branches()->where('is_main', true)->exists())->toBeTrue();

    $user = User::where('email', 'dueno@taqueria.local')->firstOrFail();
    expect($user->hasRole('super-admin'))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('la instalacion rechaza contrasenas que no coinciden', function () {
    Livewire::test('instalacion.index')
        ->set('business_name', 'Negocio')
        ->set('admin_name', 'Admin')
        ->set('admin_email', 'admin@negocio.local')
        ->set('admin_password', 'password123')
        ->set('admin_password_confirmation', 'otra-cosa')
        ->call('install')
        ->assertHasErrors(['admin_password']);

    expect(Business::query()->exists())->toBeFalse();
});

test('no se puede reinstalar una vez que ya existe un negocio', function () {
    Business::factory()->create();

    Livewire::test('instalacion.index')
        ->assertNotFound();
});
