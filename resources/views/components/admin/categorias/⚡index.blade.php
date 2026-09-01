<?php

use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $color = '#6366f1';

    public bool $is_active = true;

    public function create(): void
    {
        abort_unless(Auth::user()->can('productos.crear'), 403);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $category = ProductCategory::query()
            ->where('business_id', Auth::user()->businessId())
            ->findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->color = $category->color;
        $this->is_active = $category->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can($this->editingId ? 'productos.editar' : 'productos.crear'), 403);

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'is_active' => 'boolean',
        ]);

        $data['business_id'] = Auth::user()->businessId();

        if ($this->editingId) {
            ProductCategory::query()
                ->where('business_id', $data['business_id'])
                ->findOrFail($this->editingId)
                ->update($data);
        } else {
            ProductCategory::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->can('productos.eliminar'), 403);

        ProductCategory::query()
            ->where('business_id', Auth::user()->businessId())
            ->findOrFail($id)
            ->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'is_active']);
        $this->color = '#6366f1';
    }

    public function with(): array
    {
        return [
            'categories' => ProductCategory::query()
                ->where('business_id', Auth::user()->businessId())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Categorías</h1>
            </div>
            @can('productos.crear')
                <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                    Nueva categoría
                </button>
            @endcan
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto]">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Color</label>
                            <input type="color" wire:model="color" class="h-10 w-16 rounded-lg border border-gray-300 bg-white">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 bg-white">
                        Activa
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                            Guardar
                        </button>
                        <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Color</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="px-4 py-3">{{ $category->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block h-4 w-4 rounded-full align-middle" style="background-color: {{ $category->color }}"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $category->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('productos.editar')
                                    <button wire:click="edit({{ $category->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                @endcan
                                @can('productos.eliminar')
                                    <button wire:click="delete({{ $category->id }})" wire:confirm="¿Eliminar esta categoría?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin categorías todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $categories->links() }}
        </div>
    </div>
</div>
