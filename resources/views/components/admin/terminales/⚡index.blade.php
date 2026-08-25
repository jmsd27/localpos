<?php

use App\Models\Terminal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $ip_address = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $terminal = Terminal::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $terminal->id;
        $this->name = $terminal->name;
        $this->code = $terminal->code;
        $this->ip_address = (string) $terminal->ip_address;
        $this->is_active = $terminal->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'is_active' => 'boolean',
        ]);

        $data['ip_address'] = $data['ip_address'] !== '' ? $data['ip_address'] : null;
        $data['business_id'] = $businessId;
        $data['branch_id'] = Auth::user()->branch_id;

        if ($this->editingId) {
            Terminal::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($data);
        } else {
            Terminal::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Terminal::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'ip_address']);
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'terminals' => Terminal::query()
                ->where('business_id', Auth::user()->businessId())
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Terminales</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                Nueva terminal
            </button>
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Caja 01" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Código</label>
                            <input type="text" wire:model="code" placeholder="caja-01" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('code') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">IP (opcional)</label>
                            <input type="text" wire:model="ip_address" placeholder="192.168.1.50" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('ip_address') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" wire:model="is_active" class="rounded border-slate-700 bg-slate-800">
                        Activa
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Guardar</button>
                        <button type="button" wire:click="cancel" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($terminals as $terminal)
                        <tr>
                            <td class="px-4 py-3">{{ $terminal->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $terminal->code }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $terminal->ip_address ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $terminal->is_active ? 'bg-emerald-900 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                                    {{ $terminal->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $terminal->id }})" class="text-indigo-400 hover:text-indigo-300">Editar</button>
                                <button wire:click="delete({{ $terminal->id }})" wire:confirm="¿Eliminar esta terminal?" class="ml-3 text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin terminales todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
