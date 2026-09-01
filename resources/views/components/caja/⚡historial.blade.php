<?php

use App\Enums\CashRegisterSessionStatus;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public string $cashRegisterId = '';

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function toggle(int $sessionId): void
    {
        $this->expandedId = $this->expandedId === $sessionId ? null : $sessionId;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        $sessions = CashRegisterSession::query()
            ->whereHas('cashRegister', fn ($query) => $query->where('business_id', $businessId))
            ->when($this->cashRegisterId, fn ($query) => $query->where('cash_register_id', $this->cashRegisterId))
            ->whereDate('opened_at', '>=', $this->from)
            ->whereDate('opened_at', '<=', $this->to)
            ->with(['cashRegister', 'openedBy', 'closedBy'])
            ->latest('opened_at')
            ->paginate(15);

        $reports = app(CashRegisterService::class);

        return [
            'sessions' => $sessions,
            'registers' => CashRegister::query()->where('business_id', $businessId)->orderBy('name')->get(),
            'expandedReport' => $this->expandedId
                ? $reports->closingReport(CashRegisterSession::findOrFail($this->expandedId))
                : null,
        ];
    }
};
?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Historial de caja</h1>
        </div>
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
        <div>
            <label class="mb-1 block text-sm text-gray-600">Caja</label>
            <select wire:model.live="cashRegisterId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                <option value="">Todas</option>
                @foreach ($registers as $register)
                    <option value="{{ $register->id }}">{{ $register->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($sessions as $session)
            <div class="rounded-xl border border-gray-200 bg-white">
                <button type="button" wire:click="toggle({{ $session->id }})" class="flex w-full flex-col gap-2 p-4 text-left sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ $session->cashRegister->name }}
                            <span class="ml-2 rounded-full px-2 py-0.5 text-xs {{ $session->status === CashRegisterSessionStatus::Open ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $session->status === CashRegisterSessionStatus::Open ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </p>
                        <p class="text-xs text-gray-400">
                            Abrió {{ $session->openedBy->name }} &middot; {{ $session->opened_at->format('d/m/Y H:i') }}
                            @if ($session->closed_at)
                                &rarr; Cerró {{ $session->closedBy?->name }} &middot; {{ $session->closed_at->format('d/m/Y H:i') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-4 text-sm sm:gap-6">
                        <div class="text-right"><p class="text-xs text-gray-400">Fondo</p><p>${{ number_format((float) $session->opening_amount, 2) }}</p></div>
                        <div class="text-right"><p class="text-xs text-gray-400">Contado</p><p>{{ $session->counted_cash !== null ? '$'.number_format((float) $session->counted_cash, 2) : '—' }}</p></div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Diferencia</p>
                            <p class="{{ $session->difference === null ? '' : ((float) $session->difference < 0 ? 'text-red-600' : 'text-emerald-600') }}">
                                {{ $session->difference !== null ? '$'.number_format((float) $session->difference, 2) : '—' }}
                            </p>
                        </div>
                    </div>
                </button>

                @if ($expandedId === $session->id && $expandedReport)
                    <div class="border-t border-gray-100 p-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Ventas por método</h4>
                                <div class="space-y-1 text-sm">
                                    @foreach ($expandedReport['sales_by_method'] as $method => $total)
                                        <div class="flex justify-between"><span class="capitalize text-gray-500">{{ $method }}</span><span>${{ number_format($total, 2) }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Movimientos</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-500">Ingresos</span><span>${{ number_format($expandedReport['incomes'], 2) }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Retiros</span><span>${{ number_format($expandedReport['withdrawals'], 2) }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Ajustes</span><span>${{ number_format($expandedReport['adjustments'], 2) }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Cancelaciones</span><span>${{ number_format($expandedReport['cancellations'], 2) }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Propinas</span><span>${{ number_format($expandedReport['tips_total'], 2) }}</span></div>
                                </div>
                            </div>
                        </div>

                        @if (! empty($session->denominations))
                            <div class="mt-4 border-t border-gray-100 pt-4">
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Desglose de efectivo contado</h4>
                                <div class="grid grid-cols-2 gap-1 text-sm sm:grid-cols-4">
                                    @foreach ($session->denominations as $row)
                                        <div class="flex justify-between rounded-lg bg-gray-50 px-2.5 py-1.5">
                                            <span class="text-gray-500">{{ $row['label'] }} &times; {{ $row['quantity'] }}</span>
                                            <span class="font-medium">${{ number_format($row['subtotal'], 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($session->notes)
                            <p class="mt-4 text-sm text-gray-500"><span class="font-medium text-gray-600">Notas:</span> {{ $session->notes }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-400">
                Sin cortes de caja en este rango.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $sessions->links() }}
    </div>
</div>
