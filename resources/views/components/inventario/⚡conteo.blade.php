<?php

use App\Enums\InventoryMovementType;
use App\Models\Ingredient;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Conteo físico de inventario: lista los insumos activos con su unidad y
 * existencia en sistema para que el usuario capture la cantidad contada.
 * Al registrar, la diferencia por insumo se guarda como un movimiento de
 * tipo "ajuste" (InventoryService::adjustStock), igual que un ajuste manual
 * desde Inventario → Movimientos, pero cargando todo el conteo de una vez.
 */
new #[Layout('layouts.app')] class extends Component
{
    /** @var array<int, string> ingredient_id => cantidad contada (vacío = sin contar, se omite) */
    public array $counts = [];

    public string $reason = 'Conteo físico';

    public ?string $message = null;

    /** @var array<int, array{name:string, unit:string, previous:float, counted:float, diff:float}> */
    public array $lastResults = [];

    public function registrar(InventoryService $inventory): void
    {
        $businessId = Auth::user()->businessId();
        $reason = trim($this->reason) !== '' ? trim($this->reason) : 'Conteo físico';

        $ingredients = Ingredient::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $results = [];

        foreach ($this->counts as $id => $value) {
            if ($value === null || $value === '' || ! is_numeric($value) || (float) $value < 0) {
                continue;
            }

            $ingredient = $ingredients->get((int) $id);

            if (! $ingredient) {
                continue;
            }

            $previous = (float) $ingredient->stock;
            $counted = round((float) $value, 3);
            $diff = round($counted - $previous, 3);

            if ($diff === 0.0) {
                continue;
            }

            $inventory->adjustStock(
                $ingredient,
                InventoryMovementType::Ajuste,
                $diff,
                Auth::id(),
                reason: $reason,
            );

            $results[] = [
                'name' => $ingredient->name,
                'unit' => $ingredient->unit->label(),
                'previous' => $previous,
                'counted' => $counted,
                'diff' => $diff,
            ];
        }

        $this->reset('counts');
        $this->lastResults = $results;
        $this->message = $results === []
            ? 'No hubo diferencias para ajustar (o no se capturó ninguna cantidad).'
            : 'Se registraron '.count($results).' ajuste(s) de conteo.';
    }

    public function with(): array
    {
        return [
            'ingredients' => Ingredient::query()
                ->where('business_id', Auth::user()->businessId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div>
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Conteo físico de inventario</h1>
                <p class="mt-1 text-sm text-gray-500">Capturá la cantidad que contaste de cada insumo. Dejá en blanco los que no contaste.</p>
            </div>
            <a href="{{ route('inventario.movimientos') }}" wire:navigate class="text-sm text-violet-600 hover:text-violet-600">Ver movimientos &rarr;</a>
        </div>

        @if ($message)
            <div class="mb-6 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-700">
                {{ $message }}
            </div>
        @endif

        @if ($lastResults !== [])
            <div class="mb-6 overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Insumo</th>
                            <th class="px-4 py-3 text-right">Antes</th>
                            <th class="px-4 py-3 text-right">Contado</th>
                            <th class="px-4 py-3 text-right">Ajuste</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($lastResults as $r)
                            <tr>
                                <td class="px-4 py-3">{{ $r['name'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ number_format($r['previous'], 3) }} {{ $r['unit'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ number_format($r['counted'], 3) }} {{ $r['unit'] }}</td>
                                <td class="px-4 py-3 text-right {{ $r['diff'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ $r['diff'] > 0 ? '+' : '' }}{{ number_format($r['diff'], 3) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form wire:submit="registrar">
            <div class="mb-4 max-w-sm">
                <label class="mb-1 block text-sm text-gray-600">Motivo</label>
                <input type="text" wire:model="reason" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-violet-500 focus:outline-none">
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Insumo</th>
                            <th class="px-4 py-3">Unidad</th>
                            <th class="px-4 py-3 text-right">Existencia en sistema</th>
                            <th class="px-4 py-3 text-right">Cantidad contada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($ingredients as $ingredient)
                            <tr>
                                <td class="px-4 py-3">{{ $ingredient->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $ingredient->unit->label() }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ number_format((float) $ingredient->stock, 3) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input type="number" step="0.001" min="0" wire:model="counts.{{ $ingredient->id }}"
                                        placeholder="{{ $ingredient->unit->label() }}"
                                        class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-right text-sm text-gray-900 focus:border-violet-500 focus:outline-none">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin insumos activos todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($ingredients->isNotEmpty())
                <div class="mt-4">
                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                        Registrar ajustes
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
