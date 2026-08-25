<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $action = '';

    public string $from = '';

    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'logs' => AuditLog::query()
                ->where('business_id', $businessId)
                ->with('user')
                ->when($this->action, fn ($q) => $q->where('action', 'like', "%{$this->action}%"))
                ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
                ->latest('created_at')
                ->paginate(25),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Auditoría</h1>
            <p class="mt-1 text-sm text-slate-400">Registro inmutable de acciones sensibles: quién, qué, cuándo y desde dónde.</p>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <input type="text" wire:model.live.debounce.300ms="action" placeholder="Filtrar por acción (p. ej. venta.crear)" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            <input type="date" wire:model.live="from" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            <input type="date" wire:model.live="to" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Acción</th>
                        <th class="px-4 py-3">Registro afectado</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-slate-400">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-4 py-3">{{ $log->user?->name ?? 'Sistema' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-slate-400">
                                @if ($log->auditable_type)
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin registros de auditoría todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
