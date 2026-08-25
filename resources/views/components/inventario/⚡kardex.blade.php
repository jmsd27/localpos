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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('inventario.movimientos') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Movimientos</a>
            <h1 class="mt-1 text-2xl font-semibold">Kardex</h1>
        </div>

        <select wire:model.live="ingredientId" class="mb-4 w-full max-w-sm rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
            <option value="">Todos los insumos</option>
            @foreach ($ingredients as $ingredient)
                <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
            @endforeach
        </select>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Insumo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3 text-right">Movimiento</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $movement->ingredient->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $movement->quantity < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                {{ number_format((float) $movement->quantity, 3) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $movement->resulting_stock, 3) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Sin movimientos todavía.</td>
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
