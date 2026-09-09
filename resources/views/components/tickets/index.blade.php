<?php

use App\Enums\OrderStatus;
use App\Enums\PrintJobType;
use App\Models\Order;
use App\Models\PrintJob;
use App\Services\PrintService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $folio = '';

    public string $from = '';

    public string $to = '';

    public ?string $flash = null;

    public function mount(): void
    {
        $this->from = now()->startOfDay()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Reenvía el ticket de una venta a la cola de impresión del terminal donde
     * se cobró. Crea un PrintJob nuevo (no reintenta uno fallido): el agente
     * local de ese terminal lo levanta y lo manda a la impresora térmica.
     */
    public function reimprimir(int $orderId, PrintService $printer): void
    {
        $this->flash = null;

        $order = Order::query()
            ->where('business_id', Auth::user()->businessId())
            ->where('status', OrderStatus::Completed)
            ->findOrFail($orderId);

        $printer->enqueueSaleTicket($order);

        $this->flash = "Ticket {$order->folio} reenviado a la cola de impresión.";
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        $orders = Order::query()
            ->where('business_id', $businessId)
            ->where('status', OrderStatus::Completed)
            ->with(['user', 'payments'])
            ->when($this->folio, fn ($q) => $q->where('folio', 'like', "%{$this->folio}%"))
            ->when($this->from, fn ($q) => $q->whereDate('completed_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('completed_at', '<=', $this->to))
            ->latest('completed_at')
            ->paginate(25);

        $lastJobByOrder = PrintJob::query()
            ->where('business_id', $businessId)
            ->where('type', PrintJobType::TicketVenta)
            ->where('reference_type', (new Order)->getMorphClass())
            ->whereIn('reference_id', $orders->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('reference_id')
            ->map(fn ($jobs) => $jobs->first());

        return [
            'orders' => $orders,
            'lastJobByOrder' => $lastJobByOrder,
        ];
    }
};
?>

<div>
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Tickets</h1>
            <p class="mt-1 text-sm text-gray-500">
                El ticket se manda a la impresora térmica solo con cobrar. Desde acá lo podés volver a mandar si no salió.
            </p>
        </div>

        @if ($flash)
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                {{ $flash }}
            </div>
        @endif

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <input type="text" wire:model.live.debounce.300ms="folio" placeholder="Buscar por folio..." class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            <input type="date" wire:model.live="from" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            <input type="date" wire:model.live="to" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Cajero</th>
                        <th class="px-4 py-3">Pago</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Impresión</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        @php($job = $lastJobByOrder[$order->id] ?? null)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $order->folio }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $order->completed_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $order->user->name }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $order->payments->map(fn ($p) => $p->method->label())->unique()->implode(' + ') ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">${{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3">
                                @if (! $job)
                                    <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-400">Sin encolar</span>
                                @else
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs',
                                        'bg-emerald-50 text-emerald-700' => $job->status->value === 'impreso',
                                        'bg-amber-50 text-amber-700' => $job->status->value === 'pendiente',
                                        'bg-red-50 text-red-700' => $job->status->value === 'error',
                                    ])>{{ $job->status->label() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('ventas.ticket', $order->id) }}" target="_blank" class="text-violet-600 hover:text-violet-700">Ver</a>
                                <button wire:click="reimprimir({{ $order->id }})" class="ml-3 text-violet-600 hover:text-violet-700">Reimprimir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin tickets en este rango.</td>
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
