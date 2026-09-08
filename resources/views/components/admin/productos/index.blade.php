<?php

use App\Enums\ProductUnit;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $sku = '';

    public string $barcode = '';

    public string $description = '';

    public string $price = '';

    public string $cost_price = '';

    public string $tax_rate = '0';

    public ?int $product_category_id = null;

    public ?int $kitchen_station_id = null;

    public string $unit = 'pieza';

    public bool $is_inventoried = false;

    public bool $is_sellable = true;

    public bool $is_active = true;

    public $image = null;

    public ?string $existingImagePath = null;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        abort_unless(Auth::user()->can('productos.crear'), 403);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $product = Product::query()
            ->where('business_id', Auth::user()->businessId())
            ->findOrFail($id);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->sku = (string) $product->sku;
        $this->barcode = (string) $product->barcode;
        $this->description = (string) $product->description;
        $this->price = (string) $product->price;
        $this->cost_price = (string) $product->cost_price;
        $this->tax_rate = (string) $product->tax_rate;
        $this->product_category_id = $product->product_category_id;
        $this->kitchen_station_id = $product->kitchen_station_id;
        $this->unit = $product->unit->value;
        $this->is_inventoried = $product->is_inventoried;
        $this->is_sellable = $product->is_sellable;
        $this->is_active = $product->is_active;
        $this->existingImagePath = $product->image_path;
        $this->image = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can($this->editingId ? 'productos.editar' : 'productos.crear'), 403);

        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'kitchen_station_id' => 'nullable|exists:kitchen_stations,id',
            'unit' => 'required|string',
            'is_inventoried' => 'boolean',
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $data['sku'] = $data['sku'] !== '' ? $data['sku'] : null;
        $data['barcode'] = $data['barcode'] !== '' ? $data['barcode'] : null;
        $data['cost_price'] = $data['cost_price'] !== '' ? $data['cost_price'] : null;
        $data['business_id'] = $businessId;
        unset($data['image']);

        if ($this->image) {
            $data['image_path'] = $this->image->store('products', 'public');
        }

        if ($this->editingId) {
            Product::query()
                ->where('business_id', $businessId)
                ->findOrFail($this->editingId)
                ->update($data);
        } else {
            Product::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->can('productos.eliminar'), 403);

        Product::query()
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
        $this->reset([
            'editingId', 'name', 'sku', 'barcode', 'description', 'price', 'cost_price',
            'product_category_id', 'kitchen_station_id', 'is_inventoried', 'is_sellable', 'is_active', 'image', 'existingImagePath',
        ]);
        $this->tax_rate = '0';
        $this->unit = 'pieza';
        $this->is_sellable = true;
        $this->is_active = true;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'products' => Product::query()
                ->where('business_id', $businessId)
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
            'categories' => ProductCategory::query()->where('business_id', $businessId)->orderBy('name')->get(),
            'stations' => KitchenStation::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
            'units' => ProductUnit::cases(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Productos</h1>
            </div>
            @can('productos.crear')
                <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                    Nuevo producto
                </button>
            @endcan
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Categoría</label>
                            <select wire:model="product_category_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="">Sin categoría</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">SKU</label>
                            <input type="text" wire:model="sku" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('sku') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Código de barras</label>
                            <input type="text" wire:model="barcode" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Precio</label>
                            <input type="number" step="0.01" wire:model="price" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('price') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Precio de costo</label>
                            <input type="number" step="0.01" wire:model="cost_price" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">IVA (%)</label>
                            <input type="number" step="0.01" wire:model="tax_rate" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Unidad</label>
                            <select wire:model="unit" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                @foreach ($units as $u)
                                    <option value="{{ $u->value }}">{{ $u->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Estación</label>
                            <select wire:model="kitchen_station_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="">Sin estación</option>
                                @foreach ($stations as $station)
                                    <option value="{{ $station->id }}">{{ $station->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Descripción</label>
                        <textarea wire:model="description" rows="2" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none"></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Imagen</label>
                        <input type="file" wire:model="image" accept="image/*" class="w-full text-sm text-gray-600">
                        @error('image') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="image" class="mt-1 text-xs text-gray-500">Subiendo…</div>
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" class="mt-2 h-20 w-20 rounded-lg object-cover">
                        @elseif ($existingImagePath)
                            <img src="{{ Storage::url($existingImagePath) }}" class="mt-2 h-20 w-20 rounded-lg object-cover">
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" wire:model="is_inventoried" class="rounded border-gray-300 bg-white">
                            Inventariable
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" wire:model="is_sellable" class="rounded border-gray-300 bg-white">
                            Vendible
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 bg-white">
                            Activo
                        </label>
                    </div>

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

        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar producto…" class="mb-4 w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-violet-500 focus:outline-none">

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Precio</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($product->image_path)
                                        <img src="{{ Storage::url($product->image_path) }}" class="h-8 w-8 rounded object-cover">
                                    @endif
                                    {{ $product->name }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $product->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3">${{ number_format((float) $product->price, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $product->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('productos.editar')
                                    <button wire:click="edit({{ $product->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                @endcan
                                @can('productos.eliminar')
                                    <button wire:click="delete({{ $product->id }})" wire:confirm="¿Eliminar este producto?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400">Sin productos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>
