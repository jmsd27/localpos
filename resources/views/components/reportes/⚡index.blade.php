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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Reporte de ventas</h1>
        </div>

        <div class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-sm text-slate-300">Desde</label>
                <input type="date" wire:model.live="from" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-300">Hasta</label>
                <input type="date" wire:model.live="to" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            </div>
            @can('reportes.exportar')
                <a href="{{ route('reportes.exportar', ['from' => $from, 'to' => $to]) }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">
                    Exportar CSV
                </a>
            @endcan
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs text-slate-400">Ventas</p>
                <p class="mt-1 text-xl font-semibold">{{ $summary['orders_count'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs text-slate-400">Subtotal</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($summary['subtotal'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs text-slate-400">Descuentos</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($summary['discount_amount'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-700 bg-indigo-600/20 p-4">
                <p class="text-xs text-slate-300">Total</p>
                <p class="mt-1 text-xl font-semibold">${{ number_format($summary['total'], 2) }}</p>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-300">Por método de pago</h2>
                <ul class="space-y-1 text-sm">
                    @forelse ($summary['by_payment_method'] as $method => $total)
                        <li class="flex justify-between">
                            <span class="capitalize text-slate-400">{{ $method }}</span>
                            <span>${{ number_format($total, 2) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Sin ventas en este rango.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-300">Por cajero</h2>
                <ul class="space-y-1 text-sm">
                    @forelse ($summary['by_user'] as $row)
                        <li class="flex justify-between">
                            <span class="text-slate-400">{{ $row->user_name }} ({{ $row->orders_count }})</span>
                            <span>${{ number_format($row->total, 2) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Sin ventas en este rango.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-300">Productos más vendidos</h2>
            <table class="w-full text-left text-sm">
                <thead class="text-slate-400">
                    <tr>
                        <th class="py-1">Producto</th>
                        <th class="py-1 text-right">Cantidad</th>
                        <th class="py-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($summary['top_products'] as $product)
                        <tr>
                            <td class="py-1.5">{{ $product->name }}</td>
                            <td class="py-1.5 text-right">{{ number_format((float) $product->quantity, 2) }}</td>
                            <td class="py-1.5 text-right">${{ number_format((float) $product->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-3 text-center text-slate-500">Sin ventas en este rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
