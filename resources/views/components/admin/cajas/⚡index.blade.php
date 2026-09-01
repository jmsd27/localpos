<?php

use App\Models\CashRegister;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $register = CashRegister::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $register->id;
        $this->name = $register->name;
        $this->code = $register->code;
        $this->is_active = $register->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $data['business_id'] = $businessId;
        $data['branch_id'] = Auth::user()->branch_id;

        if ($this->editingId) {
            CashRegister::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($data);
        } else {
            CashRegister::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        CashRegister::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code']);
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'registers' => CashRegister::query()
                ->where('business_id', Auth::user()->businessId())
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Cajas</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                Nueva caja
            </button>
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Caja Principal" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Código</label>
                            <input type="text" wire:model="code" placeholder="caja-principal" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('code') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
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
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($registers as $register)
                        <tr>
                            <td class="px-4 py-3">{{ $register->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $register->code }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $register->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $register->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $register->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                <button wire:click="delete({{ $register->id }})" wire:confirm="¿Eliminar esta caja?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin cajas todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
