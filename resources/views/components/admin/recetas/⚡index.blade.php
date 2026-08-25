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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Recetas</h1>
            <p class="mt-1 text-sm text-slate-400">Solo se muestran productos marcados como "Inventariable".</p>
        </div>

        <div class="space-y-3">
            @forelse ($products as $product)
                <div class="rounded-xl border border-slate-800 bg-slate-900">
                    <button wire:click="selectProduct({{ $product->id }})" class="flex w-full items-center justify-between p-4 text-left">
                        <span class="font-medium">{{ $product->name }}</span>
                        <span class="text-xs text-slate-500">{{ $product->recipe_items_count }} {{ Str::plural('insumo', $product->recipe_items_count) }}</span>
                    </button>

                    @if ($activeProductId === $product->id)
                        <div class="border-t border-slate-800 p-4">
                            <form wire:submit="addItem" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto]">
                                <select wire:model="ingredientId" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                                    <option value="">Selecciona un insumo...</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit->label() }})</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.001" wire:model="quantity" placeholder="Cantidad" class="w-32 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm hover:bg-indigo-500">Agregar</button>
                            </form>

                            @if ($error)
                                <p class="mb-3 text-sm text-red-400">{{ $error }}</p>
                            @endif
                            @error('ingredientId') <p class="mb-3 text-sm text-red-400">{{ $message }}</p> @enderror
                            @error('quantity') <p class="mb-3 text-sm text-red-400">{{ $message }}</p> @enderror

                            <ul class="divide-y divide-slate-800">
                                @forelse ($product->recipeItems as $item)
                                    <li class="flex items-center justify-between py-2 text-sm">
                                        <span>{{ $item->ingredient->name }}</span>
                                        <span class="flex items-center gap-3 text-slate-400">
                                            {{ number_format((float) $item->quantity, 3) }} {{ $item->ingredient->unit->label() }}
                                            <button wire:click="removeItem({{ $item->id }})" wire:confirm="¿Quitar este insumo de la receta?" class="text-red-400 hover:text-red-300">Quitar</button>
                                        </span>
                                    </li>
                                @empty
                                    <li class="py-2 text-sm text-slate-500">Sin insumos en esta receta.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-6 text-center text-slate-500">
                    No hay productos marcados como inventariables. Márcalos en Productos &rarr; Inventariable.
                </div>
            @endforelse
        </div>
    </div>
</div>
