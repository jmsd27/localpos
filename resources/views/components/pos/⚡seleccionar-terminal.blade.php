<?php

use App\Models\Terminal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function select(int $terminalId): void
    {
        $terminal = Terminal::query()
            ->where('business_id', Auth::user()->businessId())
            ->where('is_active', true)
            ->findOrFail($terminalId);

        $terminal->update(['last_seen_at' => now()]);

        session(['terminal_id' => $terminal->id]);
        session()->forget('cash_register_session_id');

        $this->redirectRoute('caja.apertura', navigate: true);
    }

    public function with(): array
    {
        return [
            'terminals' => Terminal::query()
                ->where('business_id', Auth::user()->businessId())
                ->where('branch_id', Auth::user()->branch_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 text-white">
    <h1 class="mb-6 text-2xl font-semibold">Selecciona tu terminal</h1>

    <div class="grid w-full max-w-md grid-cols-2 gap-4">
        @forelse ($terminals as $terminal)
            <button wire:click="select({{ $terminal->id }})" class="rounded-xl border border-slate-800 bg-slate-900 p-6 text-center hover:border-indigo-500">
                <span class="block text-lg font-medium">{{ $terminal->name }}</span>
                <span class="block text-xs text-slate-500">{{ $terminal->code }}</span>
            </button>
        @empty
            <div class="col-span-2 rounded-xl border border-slate-800 bg-slate-900 p-6 text-center text-slate-500">
                No hay terminales activas para tu sucursal. Pide a un administrador que cree una en Configuración &rarr; Terminales.
            </div>
        @endforelse
    </div>

    <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 text-sm text-slate-400 hover:text-white">&larr; Volver al dashboard</a>
</div>
