<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.");

            return;
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', __('Estas credenciales no coinciden con nuestros registros.'));

            return;
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', 'Esta cuenta está desactivada. Contacta a un administrador.');

            return;
        }

        Auth::login($user, $this->remember);
        RateLimiter::clear($throttleKey);

        Session::regenerate();

        Auth::user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        $this->redirectRoute('dashboard', navigate: true);
    }
};
?>

<div>
    <div class="mb-7 text-center">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Bienvenido de nuevo</h1>
        <p class="mt-1 text-sm text-gray-500">Inicia sesión para administrar tu negocio</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-600" for="email">Correo</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <x-icon name="envelope" class="h-5 w-5" />
                </span>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autofocus
                    autocomplete="username"
                    placeholder="tucorreo@negocio.com"
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-gray-900 transition focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none"
                >
            </div>
            @error('email') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <div x-data="{ visible: false }">
            <label class="mb-1 block text-sm font-medium text-gray-600" for="password">Contraseña</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <x-icon name="lock-closed" class="h-5 w-5" />
                </span>
                <input
                    id="password"
                    :type="visible ? 'text' : 'password'"
                    wire:model="password"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-10 text-gray-900 transition focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none"
                >
                <button
                    type="button"
                    @click="visible = !visible"
                    tabindex="-1"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                >
                    <x-icon name="eye" class="h-5 w-5" x-show="!visible" />
                    <x-icon name="eye-slash" class="h-5 w-5" x-show="visible" x-cloak />
                </button>
            </div>
            @error('password') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" wire:model="remember" class="rounded border-gray-300 bg-white text-violet-600 focus:ring-violet-500">
            Recordarme
        </label>

        <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-violet-600 to-fuchsia-600 px-4 py-2.5 font-medium text-white shadow-lg shadow-violet-600/25 transition hover:from-violet-700 hover:to-fuchsia-700 disabled:cursor-not-allowed disabled:opacity-60"
            wire:loading.attr="disabled"
        >
            <svg wire:loading class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span wire:loading.remove>Iniciar sesión</span>
            <span wire:loading>Entrando…</span>
        </button>
    </form>
</div>
