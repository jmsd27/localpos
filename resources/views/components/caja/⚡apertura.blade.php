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

<div class="flex min-h-[70vh] flex-col items-center justify-center px-4 text-gray-900">
    <div class="w-full max-w-sm rounded-xl border border-gray-200 bg-white p-8">
        <h1 class="mb-1 text-center text-xl font-semibold">Apertura de caja</h1>
        <p class="mb-6 text-center text-sm text-gray-500">
            {{ $terminal?->name }} &middot; {{ $terminal?->cashRegister?->name }}
        </p>

        @if ($error)
            <p class="mb-4 text-center text-sm text-red-600">{{ $error }}</p>
        @endif

        @if ($terminal?->cash_register_id)
            <form wire:submit="open" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm text-gray-600">Fondo inicial</label>
                    <input type="number" step="0.01" wire:model="opening_amount" autofocus class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                    @error('opening_amount') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="w-full rounded-lg bg-violet-600 px-4 py-2 font-medium hover:bg-violet-700 text-white">
                    Abrir caja
                </button>
            </form>
        @endif

        <a href="{{ route('dashboard') }}" wire:navigate class="mt-6 block text-center text-sm text-gray-500 hover:text-gray-900">&larr; Volver al dashboard</a>
    </div>
</div>
