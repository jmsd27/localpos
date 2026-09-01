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

<div >
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Ventas</h1>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <input type="text" wire:model.live.debounce.300ms="folio" placeholder="Buscar por folio..." class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            <input type="date" wire:model.live="from" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            <input type="date" wire:model.live="to" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
        </div>

        @if ($cancelingOrderId)
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-6">
                <p class="mb-3 text-sm text-gray-700">Motivo de la anulación:</p>
                <input type="text" wire:model="cancelReason" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                @error('cancelReason') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                @if ($error)
                    <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
                @endif
                <div class="mt-3 flex gap-2">
                    <button wire:click="confirmCancel" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium hover:bg-red-500 text-white">Confirmar anulación</button>
                    <button wire:click="$set('cancelingOrderId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Volver</button>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
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
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $order->folio }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $order->completed_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $order->user->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $order->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3">${{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-emerald-50 text-emerald-700' => $order->status->value === 'completed',
                                    'bg-white text-gray-500' => $order->status->value === 'cancelled',
                                ])>
                                    {{ $order->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('ventas.ticket', $order->id) }}" target="_blank" class="text-violet-600 hover:text-violet-600">Ticket</a>
                                @can('ventas.anular')
                                    @if ($order->status->value === 'completed')
                                        <button wire:click="openCancel({{ $order->id }})" class="ml-3 text-red-600 hover:text-red-700">Anular</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin ventas en este rango.</td>
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
