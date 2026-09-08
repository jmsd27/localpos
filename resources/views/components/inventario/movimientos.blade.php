<?php

use App\Enums\InventoryMovementType;
use App\Models\Ingredient;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $ingredientId = null;

    public string $type = 'entrada';

    public string $quantity = '';

    public string $reason = '';

    public ?string $error = null;

    public function register(InventoryService $inventory): void
    {
        $this->error = null;

        $this->validate([
            'ingredientId' => 'required|exists:ingredients,id',
            'type' => 'required|in:entrada,salida,ajuste,merma',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:255',
        ]);

        $ingredient = Ingredient::query()->where('business_id', Auth::user()->businessId())->findOrFail($this->ingredientId);

        $signed = in_array($this->type, ['salida', 'merma']) ? -abs((float) $this->quantity) : abs((float) $this->quantity);

        try {
            $inventory->adjustStock(
                $ingredient,
                InventoryMovementType::from($this->type),
                $signed,
                Auth::id(),
                reason: $this->reason,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset(['quantity', 'reason']);
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'ingredients' => Ingredient::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
            'movements' => \App\Models\InventoryMovement::query()
                ->where('business_id', $businessId)
                ->whereIn('type', ['entrada', 'salida', 'ajuste', 'merma'])
                ->with(['ingredient', 'user'])
                ->latest('created_at')
                ->limit(30)
                ->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Movimientos de inventario</h1>
            </div>
            <a href="{{ route('inventario.kardex') }}" wire:navigate class="text-sm text-violet-600 hover:text-violet-600">Ver kardex &rarr;</a>
        </div>

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
            <form wire:submit="register" class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto_1fr_auto]">
                <select wire:model="ingredientId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                    <option value="">Insumo...</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                    @endforeach
                </select>
                <select wire:model="type" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                    <option value="ajuste">Ajuste</option>
                    <option value="merma">Merma</option>
                </select>
                <input type="number" step="0.001" wire:model="quantity" placeholder="Cantidad" class="w-28 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                <input type="text" wire:model="reason" placeholder="Motivo" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Registrar</button>
            </form>
            @if ($error)
                <p class="mt-3 text-sm text-red-600">{{ $error }}</p>
            @endif
            @error('ingredientId') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('quantity') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('reason') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Insumo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3 text-right">Cantidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->created_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-3">{{ $movement->ingredient->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $movement->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $movement->quantity < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                {{ number_format((float) $movement->quantity, 3) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin movimientos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
