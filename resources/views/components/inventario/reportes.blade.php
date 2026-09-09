<?php

use App\Services\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $from;

    public string $to;

    public bool $onlyLow = false;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function with(ReportService $reports): array
    {
        $businessId = Auth::user()->businessId();

        $snapshot = $reports->inventorySnapshot($businessId);
        $ingredients = $this->onlyLow
            ? $snapshot['ingredients']->where('is_low', true)->values()
            : $snapshot['ingredients'];

        $movements = $reports->inventoryMovementsSummary(
            $businessId,
            Carbon::parse($this->from)->startOfDay(),
            Carbon::parse($this->to)->endOfDay(),
        );

        return [
            'snapshot' => $snapshot,
            'ingredients' => $ingredients,
            'movements' => $movements,
        ];
    }
};
?>

<div>
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Reportes de inventario</h1>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Insumos</p>
                <p class="mt-1 text-xl font-semibold">{{ $snapshot['total_ingredients'] }}</p>
            </div>
            <div class="rounded-xl border {{ $snapshot['low_stock_count'] > 0 ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white' }} p-4">
                <p class="text-xs text-gray-500">Bajo stock</p>
                <p class="mt-1 text-xl font-semibold {{ $snapshot['low_stock_count'] > 0 ? 'text-red-600' : '' }}">{{ $snapshot['low_stock_count'] }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-600/20 p-4">
                <p class="text-xs text-gray-600">Valor total</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($snapshot['total_value'], 2) }}</p>
            </div>
        </div>

        <div class="mb-8">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-600">Existencias actuales</h2>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model.live="onlyLow" class="rounded border-gray-300">
                        Solo bajo stock
                    </label>
                    <a href="{{ route('inventario.reportes.existencias') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">
                        Exportar CSV
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Insumo</th>
                            <th class="px-4 py-3">Unidad</th>
                            <th class="px-4 py-3 text-right">Existencia</th>
                            <th class="px-4 py-3 text-right">Minimo</th>
                            <th class="px-4 py-3 text-right">Valor</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($ingredients as $row)
                            <tr>
                                <td class="px-4 py-3 {{ ! $row->is_active ? 'text-gray-400' : '' }}">{{ $row->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $row->unit }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row->stock, 3) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $row->min_stock !== null ? number_format($row->min_stock, 3) : '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->value !== null ? '$'.number_format($row->value, 2) : '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($row->is_low)
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Bajo</span>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Normal</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin insumos con bajo stock.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    <h2 class="text-sm font-semibold text-gray-600">Movimientos por rango</h2>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Desde</label>
                        <input type="date" wire:model.live="from" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Hasta</label>
                        <input type="date" wire:model.live="to" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                    </div>
                </div>
                <a href="{{ route('inventario.reportes.movimientos', ['from' => $from, 'to' => $to]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">
                    Exportar CSV
                </a>
            </div>

            <div class="mb-3 grid grid-cols-2 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs text-gray-500">Total entradas</p>
                    <p class="mt-1 text-xl font-semibold text-emerald-600">{{ number_format($movements['total_entradas'], 3) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs text-gray-500">Total salidas</p>
                    <p class="mt-1 text-xl font-semibold text-red-600">{{ number_format($movements['total_salidas'], 3) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Insumo</th>
                            <th class="px-4 py-3">Unidad</th>
                            <th class="px-4 py-3 text-right">Entradas</th>
                            <th class="px-4 py-3 text-right">Salidas</th>
                            <th class="px-4 py-3 text-right">Neto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($movements['rows'] as $row)
                            <tr>
                                <td class="px-4 py-3">{{ $row->ingredient_name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $row->ingredient_unit }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600">{{ number_format((float) $row->entradas, 3) }}</td>
                                <td class="px-4 py-3 text-right text-red-600">{{ number_format((float) $row->salidas, 3) }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $row->neto, 3) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin movimientos en este rango.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
