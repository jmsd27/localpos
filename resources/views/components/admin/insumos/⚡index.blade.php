<?php

use App\Enums\ProductUnit;
use App\Models\Ingredient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $unit = 'pieza';

    public string $initial_stock = '0';

    public string $min_stock = '';

    public string $max_stock = '';

    public string $cost_per_unit = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $ingredient = Ingredient::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $ingredient->id;
        $this->name = $ingredient->name;
        $this->unit = $ingredient->unit->value;
        $this->min_stock = (string) $ingredient->min_stock;
        $this->max_stock = (string) $ingredient->max_stock;
        $this->cost_per_unit = (string) $ingredient->cost_per_unit;
        $this->is_active = $ingredient->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string',
            'initial_stock' => 'required_without:editingId|nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $payload = [
            'business_id' => $businessId,
            'branch_id' => Auth::user()->branch_id,
            'name' => $data['name'],
            'unit' => $data['unit'],
            'min_stock' => $data['min_stock'] !== '' ? $data['min_stock'] : null,
            'max_stock' => $data['max_stock'] !== '' ? $data['max_stock'] : null,
            'cost_per_unit' => $data['cost_per_unit'] !== '' ? $data['cost_per_unit'] : null,
            'is_active' => $data['is_active'],
        ];

        if ($this->editingId) {
            Ingredient::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($payload);
        } else {
            $payload['stock'] = $data['initial_stock'] !== '' ? $data['initial_stock'] : 0;
            Ingredient::create($payload);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Ingredient::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'min_stock', 'max_stock', 'cost_per_unit']);
        $this->unit = 'pieza';
        $this->initial_stock = '0';
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'ingredients' => Ingredient::query()
                ->where('business_id', Auth::user()->businessId())
                ->orderBy('name')
                ->get(),
            'units' => ProductUnit::cases(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Insumos</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                Nuevo insumo
            </button>
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Carne de res" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Unidad</label>
                            <select wire:model="unit" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                @foreach ($units as $u)
                                    <option value="{{ $u->value }}">{{ $u->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (! $editingId)
                            <div>
                                <label class="mb-1 block text-sm text-gray-600">Existencia inicial</label>
                                <input type="number" step="0.001" wire:model="initial_stock" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                @error('initial_stock') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Existencia mínima</label>
                            <input type="number" step="0.001" wire:model="min_stock" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Existencia máxima</label>
                            <input type="number" step="0.001" wire:model="max_stock" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Costo por unidad</label>
                            <input type="number" step="0.0001" wire:model="cost_per_unit" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                    </div>

                    @if ($editingId)
                        <p class="text-xs text-gray-400">La existencia solo se ajusta desde Inventario &rarr; Movimientos.</p>
                    @endif

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 bg-white">
                        Activo
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
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Existencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($ingredients as $ingredient)
                        <tr>
                            <td class="px-4 py-3">{{ $ingredient->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $ingredient->unit->label() }}</td>
                            <td class="px-4 py-3 {{ $ingredient->min_stock !== null && (float) $ingredient->stock < (float) $ingredient->min_stock ? 'text-red-600' : '' }}">
                                {{ number_format((float) $ingredient->stock, 3) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $ingredient->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $ingredient->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $ingredient->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                <button wire:click="delete({{ $ingredient->id }})" wire:confirm="¿Eliminar este insumo?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin insumos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
