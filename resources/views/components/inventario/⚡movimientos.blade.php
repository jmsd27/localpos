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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Movimientos de inventario</h1>
            </div>
            <a href="{{ route('inventario.kardex') }}" wire:navigate class="text-sm text-indigo-400 hover:text-indigo-300">Ver kardex &rarr;</a>
        </div>

        <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <form wire:submit="register" class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto_1fr_auto]">
                <select wire:model="ingredientId" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                    <option value="">Insumo...</option>
                    @foreach ($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                    @endforeach
                </select>
                <select wire:model="type" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                    <option value="ajuste">Ajuste</option>
                    <option value="merma">Merma</option>
                </select>
                <input type="number" step="0.001" wire:model="quantity" placeholder="Cantidad" class="w-28 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                <input type="text" wire:model="reason" placeholder="Motivo" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Registrar</button>
            </form>
            @if ($error)
                <p class="mt-3 text-sm text-red-400">{{ $error }}</p>
            @endif
            @error('ingredientId') <p class="mt-3 text-sm text-red-400">{{ $message }}</p> @enderror
            @error('quantity') <p class="mt-3 text-sm text-red-400">{{ $message }}</p> @enderror
            @error('reason') <p class="mt-3 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Insumo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3 text-right">Cantidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->created_at->format('d/m H:i') }}</td>
                            <td class="px-4 py-3">{{ $movement->ingredient->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $movement->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right {{ (float) $movement->quantity < 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                {{ number_format((float) $movement->quantity, 3) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Sin movimientos todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
