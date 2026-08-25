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

<div class="min-h-screen bg-slate-950 p-8 text-white">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-white">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Compras</h1>
            </div>
            @can('compras.crear')
                <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                    Nueva compra
                </button>
            @endcan
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Proveedor</label>
                            <select wire:model="supplierId" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white">
                                <option value="">Selecciona un proveedor...</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @error('supplierId') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Notas</label>
                            <input type="text" wire:model="notes" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm text-slate-300">Insumos</label>
                        @foreach ($items as $i => $row)
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
                                <select wire:model="items.{{ $i }}.ingredient_id" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                                    <option value="">Insumo...</option>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit->label() }})</option>
                                    @endforeach
                                </select>
                                <input type="number" step="0.001" wire:model="items.{{ $i }}.quantity" placeholder="Cantidad" class="w-32 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                                <input type="number" step="0.0001" wire:model="items.{{ $i }}.unit_cost" placeholder="Costo unit." class="w-32 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white">
                                <button type="button" wire:click="removeItemRow({{ $i }})" class="text-red-400 hover:text-red-300">Quitar</button>
                            </div>
                            @error("items.{$i}.ingredient_id") <span class="block text-sm text-red-400">{{ $message }}</span> @enderror
                            @error("items.{$i}.quantity") <span class="block text-sm text-red-400">{{ $message }}</span> @enderror
                            @error("items.{$i}.unit_cost") <span class="block text-sm text-red-400">{{ $message }}</span> @enderror
                        @endforeach
                        <button type="button" wire:click="addItemRow" class="text-sm text-indigo-400 hover:text-indigo-300">+ Agregar insumo</button>
                    </div>

                    @if ($error)
                        <p class="text-sm text-red-400">{{ $error }}</p>
                    @endif

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Guardar borrador</button>
                        <button type="button" wire:click="cancelForm" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($cancelingId)
            <div class="mb-6 rounded-xl border border-red-900 bg-red-950/40 p-6">
                <p class="mb-3 text-sm text-slate-200">Motivo de la cancelación:</p>
                <input type="text" wire:model="cancelReason" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white">
                @error('cancelReason') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                <div class="mt-3 flex gap-2">
                    <button wire:click="confirmCancel" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium hover:bg-red-500">Confirmar cancelación</button>
                    <button wire:click="$set('cancelingId', null)" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Volver</button>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Proveedor</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $purchase->folio }}</td>
                            <td class="px-4 py-3">{{ $purchase->supplier->name }}</td>
                            <td class="px-4 py-3">${{ number_format((float) $purchase->total, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs',
                                    'bg-amber-900 text-amber-300' => $purchase->status->value === 'borrador',
                                    'bg-emerald-900 text-emerald-300' => $purchase->status->value === 'recibida',
                                    'bg-slate-800 text-slate-400' => $purchase->status->value === 'cancelada',
                                ])>
                                    {{ $purchase->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $purchase->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('compras.aprobar')
                                    @if ($purchase->status->value === 'borrador')
                                        <button wire:click="receive({{ $purchase->id }})" class="text-emerald-400 hover:text-emerald-300">Recibir</button>
                                        <button wire:click="openCancel({{ $purchase->id }})" class="ml-3 text-red-400 hover:text-red-300">Cancelar</button>
                                    @elseif ($purchase->status->value === 'recibida')
                                        <button wire:click="openCancel({{ $purchase->id }})" class="text-red-400 hover:text-red-300">Cancelar</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Sin compras todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
