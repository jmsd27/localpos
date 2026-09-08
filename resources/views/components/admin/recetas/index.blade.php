<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $activeProductId = null;

    public ?int $ingredientId = null;

    public string $quantity = '';

    public ?string $error = null;

    public function selectProduct(int $productId): void
    {
        $this->activeProductId = $this->activeProductId === $productId ? null : $productId;
        $this->reset(['ingredientId', 'quantity', 'error']);
    }

    public function addItem(): void
    {
        $this->error = null;

        $this->validate([
            'ingredientId' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
        ]);

        $exists = RecipeItem::where('product_id', $this->activeProductId)->where('ingredient_id', $this->ingredientId)->exists();

        if ($exists) {
            $this->error = 'Este insumo ya está en la receta.';

            return;
        }

        RecipeItem::create([
            'product_id' => $this->activeProductId,
            'ingredient_id' => $this->ingredientId,
            'quantity' => $this->quantity,
        ]);

        $this->reset(['ingredientId', 'quantity']);
    }

    public function removeItem(int $recipeItemId): void
    {
        RecipeItem::query()
            ->whereHas('product', fn ($q) => $q->where('business_id', Auth::user()->businessId()))
            ->where('id', $recipeItemId)
            ->delete();
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'products' => Product::query()
                ->where('business_id', $businessId)
                ->where('is_inventoried', true)
                ->withCount('recipeItems')
                ->with('recipeItems.ingredient')
                ->orderBy('name')
                ->get(),
            'ingredients' => Ingredient::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Recetas</h1>
            <p class="mt-1 text-sm text-gray-500">Solo se muestran productos marcados como "Inventariable".</p>
        </div>

        <div class="space-y-3">
            @forelse ($products as $product)
                <div class="rounded-xl border border-gray-200 bg-white">
                    <button wire:click="selectProduct({{ $product->id }})" class="flex w-full items-center justify-between p-4 text-left">
                        <span class="font-medium">{{ $product->name }}</span>
                        <span class="text-xs text-gray-400">{{ $product->recipe_items_count }} {{ Str::plural('insumo', $product->recipe_items_count) }}</span>
                    </button>

                    @if ($activeProductId === $product->id)
                        <div class="border-t border-gray-200 p-4">
                            <form wire:submit="addItem" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto]">
                                <select wire:model="ingredientId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                                    <option value="">Selecciona un insumo...</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit->label() }})</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.001" wire:model="quantity" placeholder="Cantidad" class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                                <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-sm hover:bg-violet-700 text-white">Agregar</button>
                            </form>

                            @if ($error)
                                <p class="mb-3 text-sm text-red-600">{{ $error }}</p>
                            @endif
                            @error('ingredientId') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('quantity') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

                            <ul class="divide-y divide-gray-100">
                                @forelse ($product->recipeItems as $item)
                                    <li class="flex items-center justify-between py-2 text-sm">
                                        <span>{{ $item->ingredient->name }}</span>
                                        <span class="flex items-center gap-3 text-gray-500">
                                            {{ number_format((float) $item->quantity, 3) }} {{ $item->ingredient->unit->label() }}
                                            <button wire:click="removeItem({{ $item->id }})" wire:confirm="¿Quitar este insumo de la receta?" class="text-red-600 hover:text-red-700">Quitar</button>
                                        </span>
                                    </li>
                                @empty
                                    <li class="py-2 text-sm text-gray-400">Sin insumos en esta receta.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-gray-400">
                    No hay productos marcados como inventariables. Márcalos en Productos &rarr; Inventariable.
                </div>
            @endforelse
        </div>
    </div>
</div>
