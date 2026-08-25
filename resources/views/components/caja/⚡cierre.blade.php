<?php

use App\Enums\PaymentMethod;
use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?CashRegisterSession $session = null;

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
    }

    public function close(CashRegisterService $cashRegisters): void
    {
        $this->error = null;

        $this->validate([
            'counted_cash' => 'required|numeric|min:0',
        ]);

        try {
            $this->session = $cashRegisters->close(
                $this->session,
                (float) $this->counted_cash,
                Auth::id(),
                $this->notes !== '' ? $this->notes : null,
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
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            @if (! $closed)
                <a href="{{ route('pos') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Volver al POS</a>
            @endif
            <h1 class="mt-1 text-2xl font-semibold">Cierre de caja</h1>
        </div>

        @if ($closed)
            <div class="rounded-xl border border-emerald-800 bg-slate-900 p-6 text-center">
                <h2 class="mb-2 text-xl font-semibold text-emerald-400">Caja cerrada</h2>
                <p class="text-slate-300">Diferencia: <span class="font-semibold">${{ number_format((float) $session->difference, 2) }}</span></p>
                <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                    Volver al dashboard
                </a>
            </div>
        @elseif ($report)
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-400">Ventas por método</h3>
                    <div class="space-y-1 text-sm">
                        @foreach ($paymentMethods as $method)
                            <div class="flex justify-between">
                                <span class="text-slate-400">{{ $method->label() }}</span>
                                <span>${{ number_format($report['sales_by_method'][$method->value] ?? 0, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-400">Efectivo</h3>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-slate-400">Fondo inicial</span><span>${{ number_format($report['opening_amount'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Ingresos</span><span>${{ number_format($report['incomes'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Retiros</span><span>${{ number_format($report['withdrawals'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Ajustes</span><span>${{ number_format($report['adjustments'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Cancelaciones</span><span>${{ number_format($report['cancellations'], 2) }}</span></div>
                        <div class="mt-2 flex justify-between border-t border-slate-800 pt-2 font-semibold"><span>Efectivo esperado</span><span>${{ number_format($report['expected_cash'], 2) }}</span></div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-400">Otros</h3>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-slate-400">Descuentos totales</span><span>${{ number_format($report['discounts_total'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Propinas totales</span><span>${{ number_format($report['tips_total'], 2) }}</span></div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
                    @if ($error)
                        <p class="mb-3 text-sm text-red-400">{{ $error }}</p>
                    @endif

                    <label class="mb-1 block text-sm text-slate-300">Efectivo contado</label>
                    <input type="number" step="0.01" wire:model="counted_cash" class="mb-3 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                    @error('counted_cash') <span class="mb-3 block text-sm text-red-400">{{ $message }}</span> @enderror

                    <label class="mb-1 block text-sm text-slate-300">Notas (opcional)</label>
                    <textarea wire:model="notes" rows="2" class="mb-4 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none"></textarea>

                    <button wire:click="close" class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold hover:bg-emerald-500">
                        Cerrar caja
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
