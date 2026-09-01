<?php

use App\Models\Table;
use App\Models\TableArea;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $table_area_id = null;

    public int $capacity = 4;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $table = Table::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $table->id;
        $this->name = $table->name;
        $this->table_area_id = $table->table_area_id;
        $this->capacity = $table->capacity;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'table_area_id' => 'required|exists:table_areas,id',
            'capacity' => 'required|integer|min:1|max:50',
        ]);

        $data['business_id'] = $businessId;
        $data['branch_id'] = Auth::user()->branch_id;

        if ($this->editingId) {
            Table::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($data);
        } else {
            $data['status'] = 'available';
            Table::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Table::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'table_area_id']);
        $this->capacity = 4;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'tables' => Table::query()->where('business_id', $businessId)->with('area')->orderBy('name')->get(),
            'areas' => TableArea::query()->where('business_id', $businessId)->orderBy('name')->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Mesas</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                Nueva mesa
            </button>
        </div>

        @if ($areas->isEmpty())
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                Primero crea un <a href="{{ route('admin.salones') }}" wire:navigate class="underline">salón</a> para poder agregar mesas.
            </div>
        @endif

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Mesa 1" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Salón</label>
                            <select wire:model="table_area_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="">Selecciona...</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                            @error('table_area_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Capacidad</label>
                            <input type="number" min="1" wire:model="capacity" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                    </div>

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
                        <th class="px-4 py-3">Salón</th>
                        <th class="px-4 py-3">Capacidad</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tables as $table)
                        <tr>
                            <td class="px-4 py-3">{{ $table->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $table->area->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $table->capacity }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $table->status->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $table->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                <button wire:click="delete({{ $table->id }})" wire:confirm="¿Eliminar esta mesa?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin mesas todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
