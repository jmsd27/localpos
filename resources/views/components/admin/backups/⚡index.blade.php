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

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Respaldos</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Genera un respaldo completo de la base de datos (.sql). También corre automáticamente cada noche.
                </p>
            </div>
            <button wire:click="generate" wire:loading.attr="disabled" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 disabled:opacity-50 text-white">
                <span wire:loading.remove wire:target="generate">Generar respaldo ahora</span>
                <span wire:loading wire:target="generate">Generando…</span>
            </button>
        </div>

        @if ($message)
            <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-600">{{ $message }}</p>
        @endif
        @if ($error)
            <p class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-600">{{ $error }}</p>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Archivo</th>
                        <th class="px-4 py-3">Tamaño</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($backups as $backup)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $backup['filename'] }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                            <td class="px-4 py-3 text-gray-500">{{ $backup['created_at']->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.backups.descargar', $backup['filename']) }}" class="text-violet-600 hover:text-violet-600">Descargar</a>
                                <button wire:click="delete('{{ $backup['filename'] }}')" wire:confirm="¿Eliminar este respaldo?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin respaldos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
