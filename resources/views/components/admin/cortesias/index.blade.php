<?php

use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $discount_type = 'percentage';

    public string $discount_value = '';

    public string $max_uses = '';

    public string $valid_from = '';

    public string $valid_until = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->code = strtoupper(Str::random(6));
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $coupon = Coupon::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $coupon->id;
        $this->code = $coupon->code;
        $this->name = $coupon->name;
        $this->discount_type = $coupon->discount_type->value;
        $this->discount_value = (string) $coupon->discount_value;
        $this->max_uses = $coupon->max_uses !== null ? (string) $coupon->max_uses : '';
        $this->valid_from = $coupon->valid_from?->format('Y-m-d') ?? '';
        $this->valid_until = $coupon->valid_until?->format('Y-m-d') ?? '';
        $this->is_active = $coupon->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(
            Auth::user()->can($this->editingId ? 'cortesias.editar' : 'cortesias.crear'),
            403
        );

        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:255',
            'discount_type' => 'required|in:percentage,amount',
            'discount_value' => 'required|numeric|min:0.01',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        if ($data['discount_type'] === 'percentage' && (float) $data['discount_value'] > 100) {
            $this->addError('discount_value', 'Un porcentaje no puede pasar de 100.');

            return;
        }

        $code = strtoupper(trim($data['code']));

        $duplicate = Coupon::query()
            ->where('business_id', $businessId)
            ->where('code', $code)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('code', 'Ya existe una cortesía con ese código.');

            return;
        }

        $payload = [
            'business_id' => $businessId,
            'code' => $code,
            'name' => $data['name'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'max_uses' => $data['max_uses'] !== '' ? (int) $data['max_uses'] : null,
            'valid_from' => $data['valid_from'] ?: null,
            'valid_until' => $data['valid_until'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->editingId) {
            Coupon::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($payload);
        } else {
            Coupon::create($payload);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        abort_unless(Auth::user()->can('cortesias.editar'), 403);

        $coupon = Coupon::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);
        $coupon->update(['is_active' => ! $coupon->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->can('cortesias.eliminar'), 403);

        $coupon = Coupon::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        // Si ya se usó, no la borramos (dejaría ventas sin referencia): la desactivamos.
        if ($coupon->used_count > 0) {
            $coupon->update(['is_active' => false]);

            return;
        }

        $coupon->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'discount_value', 'max_uses', 'valid_from', 'valid_until']);
        $this->discount_type = 'percentage';
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'coupons' => Coupon::query()
                ->where('business_id', Auth::user()->businessId())
                ->orderByDesc('is_active')
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
                <h1 class="mt-1 text-2xl font-semibold">Cortesías</h1>
                <p class="mt-1 text-sm text-gray-500">Cupones de descuento con nombre y código. El cajero los aplica al cobrar (Cortesía / cupón).</p>
            </div>
            @can('cortesias.crear')
                <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                    Nueva cortesía
                </button>
            @endcan
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Código</label>
                            <input type="text" wire:model="code" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono uppercase text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('code') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Cortesía de la casa" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Tipo de descuento</label>
                            <select wire:model="discount_type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                                <option value="percentage">Porcentaje (%)</option>
                                <option value="amount">Monto fijo ($)</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Valor</label>
                            <input type="number" step="0.01" min="0.01" wire:model="discount_value" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('discount_value') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Tope de usos (opcional)</label>
                            <input type="number" min="1" wire:model="max_uses" placeholder="Sin límite" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('max_uses') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div></div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Vigente desde (opcional)</label>
                            <input type="date" wire:model="valid_from" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Vigente hasta (opcional)</label>
                            <input type="date" wire:model="valid_until" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900">
                            @error('valid_until') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 bg-white">
                        Activa
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Guardar</button>
                        <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Descuento</th>
                        <th class="px-4 py-3">Usos</th>
                        <th class="px-4 py-3">Vigencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($coupons as $coupon)
                        <tr>
                            <td class="px-4 py-3 font-mono">{{ $coupon->code }}</td>
                            <td class="px-4 py-3">{{ $coupon->name }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($coupon->discount_type->value === 'percentage')
                                    {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.') }}%
                                @else
                                    ${{ number_format((float) $coupon->discount_value, 2) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $coupon->used_count }}{{ $coupon->max_uses !== null ? ' / '.$coupon->max_uses : '' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                @if ($coupon->valid_from || $coupon->valid_until)
                                    {{ $coupon->valid_from?->format('d/m/Y') ?? '…' }} – {{ $coupon->valid_until?->format('d/m/Y') ?? '…' }}
                                @else
                                    Sin límite
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($coupon->isRedeemable())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Vigente</span>
                                @elseif (! $coupon->is_active)
                                    <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-500">Inactiva</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">No canjeable</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @can('cortesias.editar')
                                    <button wire:click="edit({{ $coupon->id }})" class="text-violet-600 hover:text-violet-700">Editar</button>
                                    <button wire:click="toggleActive({{ $coupon->id }})" class="ml-3 text-gray-500 hover:text-gray-700">
                                        {{ $coupon->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                @endcan
                                @can('cortesias.eliminar')
                                    <button wire:click="delete({{ $coupon->id }})" wire:confirm="¿Eliminar esta cortesía?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin cortesías todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
