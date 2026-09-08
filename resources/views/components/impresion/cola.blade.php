<?php

use App\Models\PrintJob;
use App\Models\Terminal;
use App\Services\PrintService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $drawerTerminalId = null;

    public ?string $drawerMessage = null;

    public function retry(int $printJobId, PrintService $printer): void
    {
        $job = PrintJob::query()->where('business_id', Auth::user()->businessId())->findOrFail($printJobId);

        $printer->retry($job);
    }

    public function openDrawer(PrintService $printer): void
    {
        $this->drawerMessage = null;

        $this->validate(['drawerTerminalId' => 'required|exists:terminals,id']);

        $terminal = Terminal::query()->where('business_id', Auth::user()->businessId())->findOrFail($this->drawerTerminalId);

        $printer->openCashDrawer($terminal->id, Auth::id());

        $this->drawerMessage = "Apertura de cajón encolada para {$terminal->name}.";
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'jobs' => PrintJob::query()
                ->where('business_id', $businessId)
                ->with('terminal')
                ->latest()
                ->limit(50)
                ->get(),
            'terminals' => Terminal::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Cola de impresión</h1>
            <p class="mt-1 text-sm text-gray-500">
                El agente local de cada terminal sondea esta cola y envía los trabajos a su impresora térmica.
            </p>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-600">Abrir cajón manualmente</h2>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <select wire:model="drawerTerminalId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                        <option value="">Selecciona una terminal...</option>
                        @foreach ($terminals as $terminal)
                            <option value="{{ $terminal->id }}">{{ $terminal->name }}</option>
                        @endforeach
                    </select>
                    @error('drawerTerminalId') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <button wire:click="openDrawer" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                    Abrir cajón
                </button>
            </div>
            @if ($drawerMessage)
                <p class="mt-3 text-sm text-emerald-600">{{ $drawerMessage }}</p>
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Terminal</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="px-4 py-3">{{ $job->type->label() }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $job->terminal?->name ?? 'Sin asignar' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-amber-50 text-amber-700' => $job->status->value === 'pendiente',
                                    'bg-emerald-50 text-emerald-700' => $job->status->value === 'impreso',
                                    'bg-red-50 text-red-700' => $job->status->value === 'error',
                                ])>
                                    {{ $job->status->label() }}
                                </span>
                                @if ($job->status->value === 'error' && $job->error_message)
                                    <div class="mt-1 text-xs text-red-600">{{ $job->error_message }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $job->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($job->status->value === 'error')
                                    <button wire:click="retry({{ $job->id }})" class="text-violet-600 hover:text-violet-600">Reintentar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin trabajos de impresión todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
