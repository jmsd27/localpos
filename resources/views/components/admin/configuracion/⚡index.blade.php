<?php

use App\Models\Business;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $name = '';

    public string $legal_name = '';

    public string $tax_id = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $currency = '';

    public string $timezone = '';

    public string $inventario_negativo = 'permitir_alerta';

    public ?string $saved = null;

    public function mount(SettingsService $settings): void
    {
        $business = Business::findOrFail(Auth::user()->businessId());

        $this->name = $business->name;
        $this->legal_name = (string) $business->legal_name;
        $this->tax_id = (string) $business->tax_id;
        $this->address = (string) $business->address;
        $this->phone = (string) $business->phone;
        $this->email = (string) $business->email;
        $this->currency = $business->currency;
        $this->timezone = $business->timezone;

        $this->inventario_negativo = $settings->get($business->id, 'inventario_negativo', 'permitir_alerta');
    }

    public function save(SettingsService $settings): void
    {
        $this->saved = null;

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:64',
            'inventario_negativo' => 'required|in:no_permitir,permitir_alerta,permitir',
        ]);

        $business = Business::findOrFail(Auth::user()->businessId());

        $business->update([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?: null,
            'tax_id' => $data['tax_id'] ?: null,
            'address' => $data['address'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'currency' => strtoupper($data['currency']),
            'timezone' => $data['timezone'],
        ]);

        $settings->set($business->id, 'inventario_negativo', $data['inventario_negativo'], 'inventario');

        $this->saved = 'Configuración guardada.';
    }
};
?>

<div >
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
            <h1 class="mt-1 text-2xl font-semibold">Configuración del negocio</h1>
        </div>

        @if ($saved)
            <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-600">{{ $saved }}</p>
        @endif

        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-600">Datos del negocio</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Nombre comercial</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Razón social</label>
                        <input type="text" wire:model="legal_name" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">RFC / ID fiscal</label>
                        <input type="text" wire:model="tax_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Dirección</label>
                        <input type="text" wire:model="address" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
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
                        <label class="mb-1 block text-sm text-gray-600">Moneda</label>
                        <input type="text" wire:model="currency" maxlength="3" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        @error('currency') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Zona horaria</label>
                        <input type="text" wire:model="timezone" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        @error('timezone') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="mb-1 text-sm font-semibold text-gray-600">Política de inventario negativo</h2>
                <p class="mb-4 text-xs text-gray-400">Qué hacer cuando una venta o ajuste dejaría la existencia de un insumo por debajo de cero.</p>
                <select wire:model="inventario_negativo" class="w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                    <option value="no_permitir">No permitir (bloquea la operación)</option>
                    <option value="permitir_alerta">Permitir con alerta</option>
                    <option value="permitir">Permitir sin alerta</option>
                </select>
            </div>

            <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Guardar configuración</button>
        </form>
    </div>
</div>
