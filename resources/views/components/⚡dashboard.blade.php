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

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @can('ventas.crear')
                <a href="{{ route('pos') }}" wire:navigate class="rounded-xl border border-indigo-700 bg-indigo-600/20 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Punto de venta</span>
                </a>
            @endcan
            @can('productos.ver')
                <a href="{{ route('admin.categorias') }}" wire:navigate class="rounded-xl border border-slate-800 bg-slate-900 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Categorías</span>
                </a>
                <a href="{{ route('admin.productos') }}" wire:navigate class="rounded-xl border border-slate-800 bg-slate-900 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Productos</span>
                </a>
                <a href="{{ route('admin.modificadores') }}" wire:navigate class="rounded-xl border border-slate-800 bg-slate-900 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Modificadores</span>
                </a>
            @endcan
            @can('clientes.ver')
                <a href="{{ route('admin.clientes') }}" wire:navigate class="rounded-xl border border-slate-800 bg-slate-900 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Clientes</span>
                </a>
            @endcan
            @can('configuracion.editar')
                <a href="{{ route('admin.terminales') }}" wire:navigate class="rounded-xl border border-slate-800 bg-slate-900 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Terminales</span>
                </a>
                <a href="{{ route('admin.cajas') }}" wire:navigate class="rounded-xl border border-slate-800 bg-slate-900 p-4 text-center hover:border-indigo-500">
                    <span class="block text-sm font-medium">Cajas</span>
                </a>
            @endcan
        </div>
    </div>
</div>
