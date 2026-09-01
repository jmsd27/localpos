<?php

use App\Models\Ingredient;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public ?int $ingredientId = null;

    public function updatingIngredientId(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'ingredients' => Ingredient::query()->where('business_id', $businessId)->orderBy('name')->get(),
            'movements' => InventoryMovement::query()
                ->where('business_id', $businessId)
                ->when($this->ingredientId, fn ($q) => $q->where('ingredient_id', $this->ingredientId))
                ->with(['ingredient', 'user'])
                ->latest('created_at')
                ->paginate(25),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('inventario.movimientos') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Movimientos</a>
            <h1 class="mt-1 text-2xl font-semibold">Kardex</h1>
        </div>

        <select wire:model.live="ingredientId" class="mb-4 w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
            <option value="">Todos los insumos</option>
            @foreach ($ingredients as $ingredient)
                <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
            @endforeach
        </select>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Insumo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3 text-right">Movimiento</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $movement->ingredient->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $movement->quantity < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ number_format((float) $movement->quantity, 3) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $movement->resulting_stock, 3) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin movimientos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $movements->links() }}
        </div>
    </div>
</div>
