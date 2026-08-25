<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\SaleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $folio = '';

    public string $from = '';

    public string $to = '';

    public ?int $cancelingOrderId = null;

    public string $cancelReason = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->from = now()->startOfDay()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function openCancel(int $orderId): void
    {
        $this->error = null;
        $this->cancelingOrderId = $orderId;
        $this->cancelReason = '';
    }

    public function confirmCancel(SaleService $sales): void
    {
        $this->validate(['cancelReason' => 'required|string|min:3']);

        $order = Order::query()->where('business_id', Auth::user()->businessId())->findOrFail($this->cancelingOrderId);

        Gate::authorize('cancel', $order);

        if ($order->status !== OrderStatus::Completed) {
            $this->error = 'Solo se pueden anular ventas completadas.';

            return;
        }

        $sales->cancel($order, Auth::id(), $this->cancelReason);

        $this->cancelingOrderId = null;
        $this->cancelReason = '';
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'orders' => Order::query()
                ->where('business_id', $businessId)
                ->whereIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
                ->with(['user', 'customer'])
                ->when($this->folio, fn ($q) => $q->where('folio', 'like', "%{$this->folio}%"))
                ->when($this->from, fn ($q) => $q->whereDate('completed_at', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('completed_at', '<=', $this->to))
                ->latest('completed_at')
                ->paginate(25),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Ventas</h1>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <input type="text" wire:model.live.debounce.300ms="folio" placeholder="Buscar por folio..." class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            <input type="date" wire:model.live="from" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            <input type="date" wire:model.live="to" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
        </div>

        @if ($cancelingOrderId)
            <div class="mb-4 rounded-xl border border-red-900 bg-red-950/40 p-6">
                <p class="mb-3 text-sm text-slate-200">Motivo de la anulación:</p>
                <input type="text" wire:model="cancelReason" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white">
                @error('cancelReason') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                @if ($error)
                    <p class="mt-2 text-sm text-red-400">{{ $error }}</p>
                @endif
                <div class="mt-3 flex gap-2">
                    <button wire:click="confirmCancel" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium hover:bg-red-500">Confirmar anulación</button>
                    <button wire:click="$set('cancelingOrderId', null)" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Volver</button>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Cajero</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $order->folio }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $order->completed_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $order->user->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $order->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3">${{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-emerald-900 text-emerald-300' => $order->status->value === 'completed',
                                    'bg-slate-800 text-slate-400' => $order->status->value === 'cancelled',
                                ])>
                                    {{ $order->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('ventas.ticket', $order->id) }}" target="_blank" class="text-indigo-400 hover:text-indigo-300">Ticket</a>
                                @can('ventas.anular')
                                    @if ($order->status->value === 'completed')
                                        <button wire:click="openCancel({{ $order->id }})" class="ml-3 text-red-400 hover:text-red-300">Anular</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">Sin ventas en este rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    </div>
</div>
