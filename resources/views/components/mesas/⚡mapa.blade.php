<?php

use App\Enums\TableStatus;
use App\Models\Table;
use App\Models\TableArea;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        if (! session('terminal_id')) {
            $this->redirectRoute('pos.terminal', navigate: true);

            return;
        }

        if (! session('cash_register_session_id')) {
            $this->redirectRoute('caja.apertura', navigate: true);
        }
    }

    public function toggleReserve(int $tableId): void
    {
        $table = Table::query()->where('business_id', Auth::user()->businessId())->findOrFail($tableId);

        if ($table->status === TableStatus::Available) {
            $table->update(['status' => TableStatus::Reserved]);
        } elseif ($table->status === TableStatus::Reserved) {
            $table->update(['status' => TableStatus::Available]);
        }
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'areas' => TableArea::query()
                ->where('business_id', $businessId)
                ->with(['tables' => fn ($q) => $q->orderBy('name')->with(['currentOrder.user'])])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div wire:poll.5s class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('pos') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Volver al POS</a>
                <h1 class="mt-1 text-2xl font-semibold">Mapa de mesas</h1>
            </div>
            <div class="flex gap-3 text-xs text-slate-400">
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Disponible</span>
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Ocupada</span>
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Reservada</span>
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Por cobrar</span>
            </div>
        </div>

        @forelse ($areas as $area)
            <div class="mb-8">
                <h2 class="mb-3 text-sm font-semibold text-slate-400">{{ $area->name }}</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @forelse ($area->tables as $table)
                        @php
                            $colors = [
                                'available' => 'border-emerald-700 bg-emerald-950/30 hover:border-emerald-500',
                                'occupied' => 'border-amber-700 bg-amber-950/30 hover:border-amber-500',
                                'reserved' => 'border-sky-700 bg-sky-950/30 hover:border-sky-500',
                                'to_pay' => 'border-red-700 bg-red-950/30 hover:border-red-500',
                            ];
                        @endphp
                        <div class="rounded-xl border {{ $colors[$table->status->value] }} p-4">
                            <a href="{{ route('mesas.comanda', $table) }}" wire:navigate class="block">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="font-semibold">{{ $table->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $table->capacity }} pers.</span>
                                </div>
                                <div class="text-xs text-slate-400">{{ $table->status->label() }}</div>
                                @if ($table->currentOrder)
                                    <div class="mt-2 space-y-0.5 text-xs text-slate-300">
                                        <div>Mesero: {{ $table->currentOrder->user->name }}</div>
                                        <div>Abierta: {{ $table->currentOrder->created_at->format('H:i') }}</div>
                                        <div class="font-medium">${{ number_format((float) $table->currentOrder->total, 2) }}</div>
                                    </div>
                                @endif
                            </a>
                            @if (in_array($table->status->value, ['available', 'reserved']))
                                <button wire:click="toggleReserve({{ $table->id }})" class="mt-2 w-full rounded-lg border border-slate-700 py-1 text-xs text-slate-300 hover:bg-slate-800">
                                    {{ $table->status === \App\Enums\TableStatus::Reserved ? 'Liberar' : 'Reservar' }}
                                </button>
                            @endif
                        </div>
                    @empty
                        <p class="col-span-full text-sm text-slate-500">Sin mesas en este salón.</p>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-6 text-center text-slate-500">
                Sin salones configurados. Ve a Configuración &rarr; Salones para crear uno.
            </div>
        @endforelse
    </div>
</div>
