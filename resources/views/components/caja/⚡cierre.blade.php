<?php

use App\Enums\PaymentMethod;
use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * Denominaciones de moneda mexicana en circulación (billetes y monedas).
     */
    public const DENOMINATIONS = [
        ['value' => 1000, 'label' => '$1,000', 'kind' => 'billete'],
        ['value' => 500, 'label' => '$500', 'kind' => 'billete'],
        ['value' => 200, 'label' => '$200', 'kind' => 'billete'],
        ['value' => 100, 'label' => '$100', 'kind' => 'billete'],
        ['value' => 50, 'label' => '$50', 'kind' => 'billete'],
        ['value' => 20, 'label' => '$20', 'kind' => 'billete'],
        ['value' => 10, 'label' => '$10', 'kind' => 'moneda'],
        ['value' => 5, 'label' => '$5', 'kind' => 'moneda'],
        ['value' => 2, 'label' => '$2', 'kind' => 'moneda'],
        ['value' => 1, 'label' => '$1', 'kind' => 'moneda'],
        ['value' => 0.5, 'label' => '$0.50', 'kind' => 'moneda'],
    ];

    public ?CashRegisterSession $session = null;

    public string $countMode = 'denominaciones';

    /** @var array<string, int> */
    public array $quantities = [];

    public string $counted_cash = '';

    public string $notes = '';

    public ?string $error = null;

    public bool $closed = false;

    public function mount(): void
    {
        if (! session('cash_register_session_id')) {
            $this->redirectRoute('caja.apertura', navigate: true);

            return;
        }

        $this->session = CashRegisterSession::find(session('cash_register_session_id'));

        $this->quantities = array_fill(0, count(self::DENOMINATIONS), 0);
    }

    public function setCountMode(string $mode): void
    {
        $this->countMode = $mode;
    }

    public function denominationsTotal(): float
    {
        $total = 0.0;

        foreach (self::DENOMINATIONS as $index => $denomination) {
            $total += $denomination['value'] * (int) ($this->quantities[$index] ?? 0);
        }

        return round($total, 2);
    }

    private function denominationsBreakdown(): array
    {
        return collect(self::DENOMINATIONS)
            ->map(fn ($denomination, $index) => [
                'value' => $denomination['value'],
                'label' => $denomination['label'],
                'quantity' => (int) ($this->quantities[$index] ?? 0),
                'subtotal' => round($denomination['value'] * (int) ($this->quantities[$index] ?? 0), 2),
            ])
            ->filter(fn ($row) => $row['quantity'] > 0)
            ->values()
            ->all();
    }

    public function close(CashRegisterService $cashRegisters): void
    {
        $this->error = null;

        $countedCash = $this->countMode === 'denominaciones'
            ? $this->denominationsTotal()
            : (float) $this->counted_cash;

        if ($this->countMode === 'manual') {
            $this->validate(['counted_cash' => 'required|numeric|min:0']);
        }

        try {
            $this->session = $cashRegisters->close(
                $this->session,
                $countedCash,
                Auth::id(),
                $this->notes !== '' ? $this->notes : null,
                $this->countMode === 'denominaciones' ? $this->denominationsBreakdown() : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->forget(['cash_register_session_id', 'terminal_id']);

        $this->closed = true;
    }

    public function with(): array
    {
        return [
            'report' => $this->session ? app(CashRegisterService::class)->closingReport($this->session) : null,
            'paymentMethods' => PaymentMethod::cases(),
            'denominations' => self::DENOMINATIONS,
            'countedTotal' => $this->countMode === 'denominaciones' ? $this->denominationsTotal() : (float) ($this->counted_cash ?: 0),
        ];
    }
};
?>

<div>
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            @if (! $closed)
                <a href="{{ route('pos') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Volver al POS</a>
            @endif
            <h1 class="mt-1 text-2xl font-semibold">Cierre de caja</h1>
        </div>

        @if ($closed)
            <div class="rounded-xl border border-emerald-200 bg-white p-6">
                <div class="text-center">
                    <h2 class="mb-2 text-xl font-semibold text-emerald-600">Caja cerrada</h2>
                    <div class="mx-auto grid max-w-xs grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-gray-50 px-3 py-2"><p class="text-xs text-gray-500">Contado</p><p class="font-semibold">${{ number_format((float) $session->counted_cash, 2) }}</p></div>
                        <div class="rounded-lg bg-gray-50 px-3 py-2"><p class="text-xs text-gray-500">Diferencia</p><p class="font-semibold {{ (float) $session->difference < 0 ? 'text-red-600' : 'text-emerald-600' }}">${{ number_format((float) $session->difference, 2) }}</p></div>
                    </div>
                </div>

                @if (! empty($session->denominations))
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Desglose contado</h3>
                        <div class="grid grid-cols-2 gap-1 text-sm sm:grid-cols-3">
                            @foreach ($session->denominations as $row)
                                <div class="flex justify-between rounded-lg bg-gray-50 px-2.5 py-1.5">
                                    <span class="text-gray-500">{{ $row['label'] }} &times; {{ $row['quantity'] }}</span>
                                    <span class="font-medium">${{ number_format($row['subtotal'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 block rounded-lg bg-violet-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-violet-700">
                    Volver al dashboard
                </a>
            </div>
        @elseif ($report)
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Ventas por método</h3>
                    <div class="space-y-1 text-sm">
                        @foreach ($paymentMethods as $method)
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ $method->label() }}</span>
                                <span>${{ number_format($report['sales_by_method'][$method->value] ?? 0, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Efectivo</h3>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Fondo inicial</span><span>${{ number_format($report['opening_amount'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Ingresos</span><span>${{ number_format($report['incomes'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Retiros</span><span>${{ number_format($report['withdrawals'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Ajustes</span><span>${{ number_format($report['adjustments'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Cancelaciones</span><span>${{ number_format($report['cancellations'], 2) }}</span></div>
                        <div class="mt-2 flex justify-between border-t border-gray-200 pt-2 font-semibold"><span>Efectivo esperado</span><span>${{ number_format($report['expected_cash'], 2) }}</span></div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Otros</h3>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Descuentos totales</span><span>${{ number_format($report['discounts_total'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Propinas totales</span><span>${{ number_format($report['tips_total'], 2) }}</span></div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
                    @if ($error)
                        <p class="mb-3 text-sm text-red-600">{{ $error }}</p>
                    @endif

                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-500">Conteo de efectivo</h3>
                        <div class="flex rounded-lg border border-gray-200 p-0.5 text-xs">
                            <button type="button" wire:click="setCountMode('denominaciones')" class="rounded-md px-2.5 py-1 {{ $countMode === 'denominaciones' ? 'bg-violet-600 text-white' : 'text-gray-500' }}">Por denominación</button>
                            <button type="button" wire:click="setCountMode('manual')" class="rounded-md px-2.5 py-1 {{ $countMode === 'manual' ? 'bg-violet-600 text-white' : 'text-gray-500' }}">Monto directo</button>
                        </div>
                    </div>

                    @if ($countMode === 'denominaciones')
                        <div class="mb-4 grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                            @foreach ($denominations as $index => $denomination)
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-2.5 py-1.5">
                                    <span class="text-sm text-gray-600">{{ $denomination['label'] }}</span>
                                    <input
                                        type="number" min="0" step="1"
                                        wire:model.live="quantities.{{ $index }}"
                                        class="w-14 rounded-md border border-gray-300 bg-white px-1.5 py-1 text-right text-sm text-gray-900 focus:border-violet-500 focus:outline-none"
                                    >
                                </div>
                            @endforeach
                        </div>
                        <div class="mb-4 flex justify-between rounded-lg bg-violet-50 px-3 py-2 text-sm font-semibold text-violet-700">
                            <span>Total contado</span>
                            <span>${{ number_format($countedTotal, 2) }}</span>
                        </div>
                    @else
                        <label class="mb-1 block text-sm text-gray-600">Efectivo contado</label>
                        <input type="number" step="0.01" wire:model="counted_cash" class="mb-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        @error('counted_cash') <span class="mb-3 block text-sm text-red-600">{{ $message }}</span> @enderror
                    @endif

                    <label class="mb-1 block text-sm text-gray-600">Notas (opcional)</label>
                    <textarea wire:model="notes" rows="2" class="mb-4 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none"></textarea>

                    <button wire:click="close" class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold hover:bg-emerald-500 text-white">
                        Cerrar caja
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
