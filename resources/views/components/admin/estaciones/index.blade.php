<?php

use App\Models\KitchenStation;
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

    public string $color = '#6366f1';

    public ?int $printer_terminal_id = null;

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $station = KitchenStation::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $station->id;
        $this->name = $station->name;
        $this->code = $station->code;
        $this->color = $station->color;
        $this->printer_terminal_id = $station->printer_terminal_id;
        $this->is_active = $station->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'printer_terminal_id' => 'nullable|exists:terminals,id',
            'is_active' => 'boolean',
        ]);

        $data['business_id'] = $businessId;
        $data['branch_id'] = Auth::user()->branch_id;

        if ($this->editingId) {
            KitchenStation::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($data);
        } else {
            KitchenStation::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        KitchenStation::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'printer_terminal_id']);
        $this->color = '#6366f1';
        $this->is_active = true;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'stations' => KitchenStation::query()
                ->where('business_id', $businessId)
                ->with('printerTerminal')
                ->orderBy('name')
                ->get(),
            'terminals' => Terminal::query()->where('business_id', $businessId)->orderBy('name')->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Estaciones</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                Nueva estación
            </button>
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_1fr_auto]">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Cocina" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Código</label>
                            <input type="text" wire:model="code" placeholder="cocina" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('code') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Color</label>
                            <input type="color" wire:model="color" class="h-10 w-16 rounded-lg border border-gray-300 bg-white">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Impresora de comandas (opcional)</label>
                        <select wire:model="printer_terminal_id" class="w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                            <option value="">Sin impresora asignada</option>
                            @foreach ($terminals as $terminal)
                                <option value="{{ $terminal->id }}">{{ $terminal->name }}{{ $terminal->printer_name ? " ({$terminal->printer_name})" : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">El terminal cuyo agente local imprimirá las comandas de esta estación.</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 bg-white">
                        Activa
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Guardar</button>
                        <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Color</th>
                        <th class="px-4 py-3">Impresora</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($stations as $station)
                        <tr>
                            <td class="px-4 py-3">{{ $station->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $station->code }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block h-4 w-4 rounded-full align-middle" style="background-color: {{ $station->color }}"></span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $station->printerTerminal?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $station->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $station->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $station->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                <button wire:click="delete({{ $station->id }})" wire:confirm="¿Eliminar esta estación?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin estaciones todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
