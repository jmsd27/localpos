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

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function with(ReportService $reports): array
    {
        $summary = $reports->salesSummary(
            Auth::user()->businessId(),
            Carbon::parse($this->from)->startOfDay(),
            Carbon::parse($this->to)->endOfDay(),
        );

        return ['summary' => $summary];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Reporte de ventas</h1>
        </div>

        <div class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-sm text-gray-600">Desde</label>
                <input type="date" wire:model.live="from" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            </div>
            <div>
                <label class="mb-1 block text-sm text-gray-600">Hasta</label>
                <input type="date" wire:model.live="to" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            </div>
            @can('reportes.exportar')
                <a href="{{ route('reportes.exportar', ['from' => $from, 'to' => $to]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">
                    Exportar CSV
                </a>
            @endcan
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Ventas</p>
                <p class="mt-1 text-xl font-semibold">{{ $summary['orders_count'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Subtotal</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($summary['subtotal'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">Descuentos</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($summary['discount_amount'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-600/20 p-4">
                <p class="text-xs text-gray-600">Total</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($summary['total'], 2) }}</p>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-gray-600">Por método de pago</h2>
                <ul class="space-y-1 text-sm">
                    @forelse ($summary['by_payment_method'] as $method => $total)
                        <li class="flex justify-between">
                            <span class="capitalize text-gray-500">{{ $method }}</span>
                            <span>${{ number_format($total, 2) }}</span>
                        </li>
                    @empty
                        <li class="text-gray-400">Sin ventas en este rango.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold text-gray-600">Por cajero</h2>
                <ul class="space-y-1 text-sm">
                    @forelse ($summary['by_user'] as $row)
                        <li class="flex justify-between">
                            <span class="text-gray-500">{{ $row->user_name }} ({{ $row->orders_count }})</span>
                            <span>${{ number_format($row->total, 2) }}</span>
                        </li>
                    @empty
                        <li class="text-gray-400">Sin ventas en este rango.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-gray-600">Productos más vendidos</h2>
            <table class="w-full text-left text-sm">
                <thead class="text-gray-500">
                    <tr>
                        <th class="py-1">Producto</th>
                        <th class="py-1 text-right">Cantidad</th>
                        <th class="py-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($summary['top_products'] as $product)
                        <tr>
                            <td class="py-1.5">{{ $product->name }}</td>
                            <td class="py-1.5 text-right">{{ number_format((float) $product->quantity, 2) }}</td>
                            <td class="py-1.5 text-right">${{ number_format((float) $product->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-3 text-center text-gray-400">Sin ventas en este rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
