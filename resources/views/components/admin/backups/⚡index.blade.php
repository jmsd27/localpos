<?php

use App\Services\BackupService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $error = null;

    public ?string $message = null;

    public function generate(BackupService $backups): void
    {
        $this->error = null;
        $this->message = null;

        try {
            $result = $backups->run();
            $this->message = "Respaldo generado: {$result['filename']}.";
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function delete(string $filename, BackupService $backups): void
    {
        $backups->delete($filename);
    }

    public function with(BackupService $backups): array
    {
        return [
            'backups' => $backups->list(),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Respaldos</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Genera un respaldo completo de la base de datos (.sql). También corre automáticamente cada noche.
                </p>
            </div>
            <button wire:click="generate" wire:loading.attr="disabled" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500 disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">Generar respaldo ahora</span>
                <span wire:loading wire:target="generate">Generando…</span>
            </button>
        </div>

        @if ($message)
            <p class="mb-4 rounded-lg border border-emerald-800 bg-emerald-950/30 p-3 text-sm text-emerald-400">{{ $message }}</p>
        @endif
        @if ($error)
            <p class="mb-4 rounded-lg border border-red-800 bg-red-950/30 p-3 text-sm text-red-400">{{ $error }}</p>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Archivo</th>
                        <th class="px-4 py-3">Tamaño</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($backups as $backup)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $backup['filename'] }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                            <td class="px-4 py-3 text-slate-400">{{ $backup['created_at']->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.backups.descargar', $backup['filename']) }}" class="text-indigo-400 hover:text-indigo-300">Descargar</a>
                                <button wire:click="delete('{{ $backup['filename'] }}')" wire:confirm="¿Eliminar este respaldo?" class="ml-3 text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">Sin respaldos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
