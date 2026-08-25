<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate();

        if (! Auth::attempt($credentials, $this->remember)) {
            $this->addError('email', __('Estas credenciales no coinciden con nuestros registros.'));

            return;
        }

        Session::regenerate();

        Auth::user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        $this->redirectRoute('dashboard', navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-slate-950 px-4">
    <div class="w-full max-w-sm rounded-xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
        <h1 class="mb-6 text-center text-2xl font-semibold text-white">LOCALPOS</h1>

        <form wire:submit="login" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm text-slate-300" for="email">Correo</label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none"
                >
                @error('email') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm text-slate-300" for="password">Contraseña</label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none"
                >
                @error('password') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" wire:model="remember" class="rounded border-slate-700 bg-slate-800">
                Recordarme
            </label>

            <button
                type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white transition hover:bg-indigo-500"
                wire:loading.attr="disabled"
            >
                Iniciar sesión
            </button>
        </form>
    </div>
</div>
