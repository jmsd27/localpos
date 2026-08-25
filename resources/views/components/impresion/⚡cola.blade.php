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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Cola de impresión</h1>
            <p class="mt-1 text-sm text-slate-400">
                El agente local de cada terminal sondea esta cola y envía los trabajos a su impresora térmica.
            </p>
        </div>

        <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="mb-3 text-sm font-semibold text-slate-300">Abrir cajón manualmente</h2>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <select wire:model="drawerTerminalId" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                        <option value="">Selecciona una terminal...</option>
                        @foreach ($terminals as $terminal)
                            <option value="{{ $terminal->id }}">{{ $terminal->name }}</option>
                        @endforeach
                    </select>
                    @error('drawerTerminalId') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                </div>
                <button wire:click="openDrawer" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                    Abrir cajón
                </button>
            </div>
            @if ($drawerMessage)
                <p class="mt-3 text-sm text-emerald-400">{{ $drawerMessage }}</p>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Terminal</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="px-4 py-3">{{ $job->type->label() }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $job->terminal?->name ?? 'Sin asignar' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-amber-900 text-amber-300' => $job->status->value === 'pendiente',
                                    'bg-emerald-900 text-emerald-300' => $job->status->value === 'impreso',
                                    'bg-red-900 text-red-300' => $job->status->value === 'error',
                                ])>
                                    {{ $job->status->label() }}
                                </span>
                                @if ($job->status->value === 'error' && $job->error_message)
                                    <div class="mt-1 text-xs text-red-400">{{ $job->error_message }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $job->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($job->status->value === 'error')
                                    <button wire:click="retry({{ $job->id }})" class="text-indigo-400 hover:text-indigo-300">Reintentar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin trabajos de impresión todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
