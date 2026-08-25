<?php

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $contact_name = '';

    public string $phone = '';

    public string $email = '';

    public string $tax_id = '';

    public string $address = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $supplier = Supplier::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->contact_name = (string) $supplier->contact_name;
        $this->phone = (string) $supplier->phone;
        $this->email = (string) $supplier->email;
        $this->tax_id = (string) $supplier->tax_id;
        $this->address = (string) $supplier->address;
        $this->is_active = $supplier->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tax_id' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $payload = [
            'business_id' => $businessId,
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'tax_id' => $data['tax_id'] ?: null,
            'address' => $data['address'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->editingId) {
            Supplier::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($payload);
        } else {
            Supplier::create($payload);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Supplier::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'contact_name', 'phone', 'email', 'tax_id', 'address']);
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->where('business_id', Auth::user()->businessId())
                ->orderBy('name')
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
                <h1 class="mt-1 text-2xl font-semibold">Proveedores</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">
                Nuevo proveedor
            </button>
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Distribuidora Norte" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Contacto</label>
                            <input type="text" wire:model="contact_name" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Teléfono</label>
                            <input type="text" wire:model="phone" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Correo</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('email') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">RFC / ID fiscal</label>
                            <input type="text" wire:model="tax_id" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm text-slate-300">Dirección</label>
                            <input type="text" wire:model="address" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" wire:model="is_active" class="rounded border-slate-700 bg-slate-800">
                        Activo
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium hover:bg-indigo-500">Guardar</button>
                        <button type="button" wire:click="cancel" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Contacto</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-950">
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td class="px-4 py-3">{{ $supplier->name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $supplier->contact_name ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $supplier->phone ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $supplier->is_active ? 'bg-emerald-900 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                                    {{ $supplier->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $supplier->id }})" class="text-indigo-400 hover:text-indigo-300">Editar</button>
                                <button wire:click="delete({{ $supplier->id }})" wire:confirm="¿Eliminar este proveedor?" class="ml-3 text-red-400 hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sin proveedores todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
