<?php

use App\Enums\CashMovementType;
use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?CashRegisterSession $session = null;

    public string $type = 'ingreso';

    public string $amount = '';

    public string $reason = '';

    public ?string $error = null;

    public function mount(): void
    {
        if (! session('cash_register_session_id')) {
            $this->redirectRoute('caja.apertura', navigate: true);

            return;
        }

        $this->session = CashRegisterSession::find(session('cash_register_session_id'));
    }

    public function register(CashRegisterService $cashRegisters): void
    {
        $this->error = null;

        $this->validate([
            'type' => 'required|in:ingreso,retiro,ajuste',
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        $amount = (float) $this->amount;

        if ($this->type === 'retiro') {
            $amount = -abs($amount);
        } elseif ($this->type === 'ingreso') {
            $amount = abs($amount);
        }

        $cashRegisters->addMovement(
            $this->session,
            CashMovementType::from($this->type),
            $amount,
            Auth::id(),
            reason: $this->reason,
        );

        $this->reset(['amount', 'reason']);
        $this->session->refresh();
    }

    public function with(): array
    {
        return [
            'movements' => $this->session?->movements()->with('user')->latest('created_at')->get() ?? collect(),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('pos') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Volver al POS</a>
                <h1 class="mt-1 text-2xl font-semibold">Movimientos de caja</h1>
            </div>
        </div>

        @can('caja.registrar_movimiento')
            <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
                <form wire:submit="register" class="grid grid-cols-1 gap-4 sm:grid-cols-[auto_auto_1fr_auto]">
                    <select wire:model="type" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                        <option value="ingreso">Ingreso</option>
                        <option value="retiro">Retiro</option>
                        <option value="ajuste">Ajuste</option>
                    </select>
                    <input type="number" step="0.01" wire:model="amount" placeholder="Monto" class="w-32 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                    <input type="text" wire:model="reason" placeholder="Motivo" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Registrar</button>
                </form>
                @error('amount') <span class="mt-2 block text-sm text-red-400">{{ $message }}</span> @enderror
                @error('reason') <span class="mt-2 block text-sm text-red-400">{{ $message }}</span> @enderror
            </div>
        @endcan

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Método</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3 text-right">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->created_at->format('H:i') }}</td>
                            <td class="px-4 py-3">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->payment_method->label() }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->user->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $movement->amount < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                ${{ number_format((float) $movement->amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Sin movimientos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
