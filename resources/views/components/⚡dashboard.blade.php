<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function logout(): void
    {
        Auth::logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirectRoute('login', navigate: true);
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">LOCALPOS</h1>
            <button wire:click="logout" class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800">
                Cerrar sesión
            </button>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-300">
                Sesión iniciada como <span class="font-medium text-white">{{ Auth::user()->name }}</span>
                ({{ Auth::user()->email }}).
            </p>
            <p class="mt-2 text-sm text-slate-400">
                Roles: {{ Auth::user()->getRoleNames()->join(', ') ?: 'sin rol asignado' }}
            </p>
        </div>
    </div>
</div>
