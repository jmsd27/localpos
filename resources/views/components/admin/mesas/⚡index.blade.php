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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Mesas</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                Nueva mesa
            </button>
        </div>

        @if ($areas->isEmpty())
            <div class="mb-6 rounded-xl border border-amber-800 bg-amber-950/30 p-4 text-sm text-amber-300">
                Primero crea un <a href="{{ route('admin.salones') }}" wire:navigate class="underline">salón</a> para poder agregar mesas.
            </div>
        @endif

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Mesa 1" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Salón</label>
                            <select wire:model="table_area_id" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                                <option value="">Selecciona...</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                            @error('table_area_id') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Capacidad</label>
                            <input type="number" min="1" wire:model="capacity" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

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
                        <th class="px-4 py-3">Salón</th>
                        <th class="px-4 py-3">Capacidad</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($tables as $table)
                        <tr>
                            <td class="px-4 py-3">{{ $table->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $table->area->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $table->capacity }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $table->status->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $table->id }})" class="text-indigo-400 hover:text-indigo-300">Editar</button>
                                <button wire:click="delete({{ $table->id }})" wire:confirm="¿Eliminar esta mesa?" class="ml-3 text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin mesas todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
