<?php

use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $supplierId = null;

    public string $notes = '';

    public array $items = [];

    public ?string $error = null;

    public ?int $cancelingId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->items = [['ingredient_id' => '', 'quantity' => '', 'unit_cost' => '']];
    }

    public function create(): void
    {
        abort_unless(Auth::user()->can('compras.crear'), 403);

        $this->reset(['supplierId', 'notes', 'error']);
        $this->items = [['ingredient_id' => '', 'quantity' => '', 'unit_cost' => '']];
        $this->showForm = true;
    }

    public function addItemRow(): void
    {
        $this->items[] = ['ingredient_id' => '', 'quantity' => '', 'unit_cost' => ''];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can('compras.crear'), 403);

        $this->error = null;

        $this->validate([
            'supplierId' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();

        try {
            app(PurchaseService::class)->create([
                'business_id' => $user->businessId(),
                'branch_id' => $user->branch_id,
                'supplier_id' => $this->supplierId,
                'user_id' => $user->id,
                'notes' => $this->notes ?: null,
                'items' => $this->items,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->showForm = false;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
    }

    public function receive(int $purchaseId): void
    {
        abort_unless(Auth::user()->can('compras.aprobar'), 403);

        $purchase = Purchase::query()->where('business_id', Auth::user()->businessId())->findOrFail($purchaseId);

        app(PurchaseService::class)->receive($purchase, Auth::id());
    }

    public function openCancel(int $purchaseId): void
    {
        abort_unless(Auth::user()->can('compras.aprobar'), 403);

        $this->cancelingId = $purchaseId;
        $this->cancelReason = '';
    }

    public function confirmCancel(): void
    {
        abort_unless(Auth::user()->can('compras.aprobar'), 403);

        $this->validate(['cancelReason' => 'required|string|min:3']);

        $purchase = Purchase::query()->where('business_id', Auth::user()->businessId())->findOrFail($this->cancelingId);

        app(PurchaseService::class)->cancel($purchase, Auth::id(), $this->cancelReason);

        $this->cancelingId = null;
        $this->cancelReason = '';
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'purchases' => Purchase::query()
                ->where('business_id', $businessId)
                ->with('supplier')
                ->latest()
                ->get(),
            'suppliers' => Supplier::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
            'ingredients' => Ingredient::query()->where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Compras</h1>
            </div>
            @can('compras.crear')
                <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                    Nueva compra
                </button>
            @endcan
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Proveedor</label>
                            <select wire:model="supplierId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                                <option value="">Selecciona un proveedor...</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @error('supplierId') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Notas</label>
                            <input type="text" wire:model="notes" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm text-gray-600">Insumos</label>
                        @foreach ($items as $i => $row)
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
                                <select wire:model="items.{{ $i }}.ingredient_id" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                                    <option value="">Insumo...</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit->label() }})</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.001" wire:model="items.{{ $i }}.quantity" placeholder="Cantidad" class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                                <input type="number" step="0.0001" wire:model="items.{{ $i }}.unit_cost" placeholder="Costo unit." class="w-32 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                                <button type="button" wire:click="removeItemRow({{ $i }})" class="text-red-600 hover:text-red-700">Quitar</button>
                            </div>
                            @error("items.{$i}.ingredient_id") <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
                            @error("items.{$i}.quantity") <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
                            @error("items.{$i}.unit_cost") <span class="block text-sm text-red-600">{{ $message }}</span> @enderror
                        @endforeach
                        <button type="button" wire:click="addItemRow" class="text-sm text-violet-600 hover:text-violet-600">+ Agregar insumo</button>
                    </div>

                    @if ($error)
                        <p class="text-sm text-red-600">{{ $error }}</p>
                    @endif

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Guardar borrador</button>
                        <button type="button" wire:click="cancelForm" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($cancelingId)
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-6">
                <p class="mb-3 text-sm text-gray-700">Motivo de la cancelación:</p>
                <input type="text" wire:model="cancelReason" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                @error('cancelReason') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="confirmCancel" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium hover:bg-red-500 text-white">Confirmar cancelación</button>
                    <button wire:click="$set('cancelingId', null)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Volver</button>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Proveedor</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $purchase->folio }}</td>
                            <td class="px-4 py-3">{{ $purchase->supplier->name }}</td>
                            <td class="px-4 py-3">${{ number_format((float) $purchase->total, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-amber-50 text-amber-700' => $purchase->status->value === 'borrador',
                                    'bg-emerald-50 text-emerald-700' => $purchase->status->value === 'recibida',
                                    'bg-white text-gray-500' => $purchase->status->value === 'cancelada',
                                ])>
                                    {{ $purchase->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $purchase->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('compras.aprobar')
                                    @if ($purchase->status->value === 'borrador')
                                        <button wire:click="receive({{ $purchase->id }})" class="text-emerald-600 hover:text-emerald-700">Recibir</button>
                                        <button wire:click="openCancel({{ $purchase->id }})" class="ml-3 text-red-600 hover:text-red-700">Cancelar</button>
                                    @elseif ($purchase->status->value === 'recibida')
                                        <button wire:click="openCancel({{ $purchase->id }})" class="text-red-600 hover:text-red-700">Cancelar</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin compras todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
