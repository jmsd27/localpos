<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component
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

<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" @click.outside="open = false" type="button"
        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </span>
        <span class="hidden text-left sm:block">
            <span class="block font-medium text-gray-800">{{ Auth::user()->name }}</span>
            <span class="block text-xs text-gray-400">{{ Auth::user()->getRoleNames()->first() ?? 'Sin rol' }}</span>
        </span>
        <x-icon name="chevron-down" class="h-4 w-4 text-gray-400" />
    </button>

    <div x-show="open" x-transition x-cloak
        class="absolute right-0 z-30 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
        <div class="border-b border-gray-100 px-3 py-2">
            <p class="truncate text-sm font-medium text-gray-800">{{ Auth::user()->name }}</p>
            <p class="truncate text-xs text-gray-400">{{ Auth::user()->email }}</p>
        </div>
        <button wire:click="logout" type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
            <x-icon name="arrow-left-on-rectangle" class="h-4 w-4" />
            Cerrar sesión
        </button>
    </div>
</div>
