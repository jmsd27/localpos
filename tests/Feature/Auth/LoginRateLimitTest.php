<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('despues de 5 intentos fallidos el sexto queda bloqueado temporalmente', function () {
    $user = User::factory()->create(['email' => 'cajero@localpos.local', 'password' => Hash::make('password-correcto')]);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password-incorrecto')
            ->call('login')
            ->assertHasErrors('email');
    }

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password-correcto')
        ->call('login')
        ->assertSee('Demasiados intentos');

    $this->assertGuest();
});

test('un login correcto limpia el contador de intentos fallidos previos', function () {
    $user = User::factory()->create(['email' => 'mesero@localpos.local', 'password' => Hash::make('password-correcto')]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password-incorrecto')
        ->call('login')
        ->assertHasErrors('email');

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password-correcto')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});
