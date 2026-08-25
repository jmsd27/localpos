<?php

use App\Models\User;
use Livewire\Livewire;

test('un usuario sembrado puede iniciar sesión con credenciales correctas', function () {
    $user = User::factory()->create([
        'password' => 'secreto123',
    ]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'secreto123')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

test('una contraseña incorrecta es rechazada', function () {
    $user = User::factory()->create([
        'password' => 'secreto123',
    ]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'contraseña-incorrecta')
        ->call('login')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

test('una ruta protegida redirige al login si no hay sesión', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
