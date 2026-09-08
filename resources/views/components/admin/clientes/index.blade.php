<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $tax_id = '';

    public string $address = '';

    public string $notes = '';

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        abort_unless(Auth::user()->can('clientes.crear'), 403);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $customer = Customer::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->phone = (string) $customer->phone;
        $this->email = (string) $customer->email;
        $this->tax_id = (string) $customer->tax_id;
        $this->address = (string) $customer->address;
        $this->notes = (string) $customer->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can($this->editingId ? 'clientes.editar' : 'clientes.crear'), 403);

        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'tax_id' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        foreach (['phone', 'email', 'tax_id', 'address', 'notes'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        $data['business_id'] = $businessId;

        if ($this->editingId) {
            Customer::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($data);
        } else {
            Customer::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->can('clientes.eliminar'), 403);

        Customer::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'email', 'tax_id', 'address', 'notes']);
    }

    public function with(): array
    {
        return [
            'customers' => Customer::query()
                ->where('business_id', Auth::user()->businessId())
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Clientes</h1>
            </div>
            @can('clientes.crear')
                <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                    Nuevo cliente
                </button>
            @endcan
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Teléfono</label>
                            <input type="text" wire:model="phone" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Correo</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('email') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">RFC</label>
                            <input type="text" wire:model="tax_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Dirección</label>
                            <input type="text" wire:model="address" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm text-gray-600">Notas</label>
                            <textarea wire:model="notes" rows="2" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none"></textarea>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Guardar</button>
                        <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-white">Cancelar</button>
                    </div>
                </form>
            </div>
        @endif

        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar cliente…" class="mb-4 w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-violet-500 focus:outline-none">

        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="px-4 py-3">{{ $customer->name }}</td>
                            <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $customer->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('clientes.editar')
                                    <button wire:click="edit({{ $customer->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                @endcan
                                @can('clientes.eliminar')
                                    <button wire:click="delete({{ $customer->id }})" wire:confirm="¿Eliminar este cliente?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">Sin clientes todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    </div>
</div>
