<?php

use App\Enums\CashRegisterSessionStatus;
use App\Models\CashRegisterSession;
use App\Models\Terminal;
use App\Services\CashRegisterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $opening_amount = '';

    public ?string $error = null;

    public ?Terminal $terminal = null;

    public function mount(): void
    {
        if (! session('terminal_id')) {
            $this->redirectRoute('pos.terminal', navigate: true);

            return;
        }

        $this->terminal = Terminal::with('cashRegister')->find(session('terminal_id'));

        if (! $this->terminal || ! $this->terminal->cash_register_id) {
            $this->error = 'Esta terminal no tiene una caja asociada. Pide a un administrador que la configure en Configuración → Terminales.';

            return;
        }

        $openSession = CashRegisterSession::query()
            ->where('cash_register_id', $this->terminal->cash_register_id)
            ->where('status', CashRegisterSessionStatus::Open)
            ->first();

        if ($openSession) {
            session(['cash_register_session_id' => $openSession->id]);
            $this->redirectRoute('pos', navigate: true);
        }
    }

    public function open(CashRegisterService $cashRegisters): void
    {
        $this->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        try {
            $session = $cashRegisters->open(
                $this->terminal->cash_register_id,
                $this->terminal->id,
                Auth::id(),
                (float) $this->opening_amount,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session(['cash_register_session_id' => $session->id]);

        $this->redirectRoute('pos', navigate: true);
    }
};
?>

<div class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 text-white">
    <div class="w-full max-w-sm rounded-xl border border-slate-800 bg-slate-900 p-8">
        <h1 class="mb-1 text-center text-xl font-semibold">Apertura de caja</h1>
        <p class="mb-6 text-center text-sm text-slate-400">
            {{ $terminal?->name }} &middot; {{ $terminal?->cashRegister?->name }}
        </p>

        @if ($error)
            <p class="mb-4 text-center text-sm text-red-400">{{ $error }}</p>
        @endif

        @if ($terminal?->cash_register_id)
            <form wire:submit="open" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm text-slate-300">Fondo inicial</label>
                    <input type="number" step="0.01" wire:model="opening_amount" autofocus class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                    @error('opening_amount') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium hover:bg-indigo-500">
                    Abrir caja
                </button>
            </form>
        @endif

        <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 block text-center text-sm text-slate-400 hover:text-white">&larr; Volver al dashboard</a>
    </div>
</div>
