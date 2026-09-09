<?php

use App\Models\Business;
use App\Services\SettingsService;
use App\Services\TicketLogoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $legal_name = '';

    public string $tax_id = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $currency = '';

    public string $timezone = '';

    public string $inventario_negativo = 'permitir_alerta';

    public int $ticket_ancho = 48;

    public int $ticket_feed = 3;

    public string $ticket_pie = '';

    public $ticket_logo = null;

    public ?string $ticket_logo_preview = null;

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
        $this->ticket_ancho = (int) $settings->get($business->id, 'ticket_ancho', 48);
        $this->ticket_feed = (int) $settings->get($business->id, 'ticket_feed', 3);
        $this->ticket_pie = (string) $settings->get($business->id, 'ticket_pie', '¡Gracias por su compra!');
        $this->ticket_logo_preview = $settings->get($business->id, 'ticket_logo_preview') ?: null;
    }

    public function save(SettingsService $settings, TicketLogoService $logos): void
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
            'ticket_ancho' => 'required|integer|in:32,42,48',
            'ticket_feed' => 'required|integer|between:0,10',
            'ticket_pie' => 'nullable|string|max:120',
            'ticket_logo' => 'nullable|image|max:2048',
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
        $settings->set($business->id, 'ticket_ancho', (string) $data['ticket_ancho'], 'ticket');
        $settings->set($business->id, 'ticket_feed', (string) $data['ticket_feed'], 'ticket');
        $settings->set($business->id, 'ticket_pie', trim((string) ($data['ticket_pie'] ?? '')), 'ticket');

        if ($this->ticket_logo) {
            $result = $logos->fromImage(file_get_contents($this->ticket_logo->getRealPath()), (int) $data['ticket_ancho']);
            $path = "ticket-logos/{$business->id}.png";
            Storage::disk('public')->put($path, $result['preview_png']);

            $settings->set($business->id, 'ticket_logo_escpos', $result['escpos'], 'ticket');
            $settings->set($business->id, 'ticket_logo_preview', $path, 'ticket');

            $this->ticket_logo_preview = $path;
            $this->ticket_logo = null;
        }

        $this->saved = 'Configuración guardada.';
    }

    public function quitarLogo(SettingsService $settings): void
    {
        $business = Business::findOrFail(Auth::user()->businessId());

        if ($this->ticket_logo_preview) {
            Storage::disk('public')->delete($this->ticket_logo_preview);
        }

        $settings->set($business->id, 'ticket_logo_escpos', null, 'ticket');
        $settings->set($business->id, 'ticket_logo_preview', null, 'ticket');

        $this->ticket_logo = null;
        $this->ticket_logo_preview = null;
        $this->saved = 'Logo quitado.';
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

            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="mb-1 text-sm font-semibold text-gray-600">Ticket de venta</h2>
                <p class="mb-4 text-xs text-gray-400">Logo, ancho del papel y avance antes del corte para la impresora térmica. Este es el ancho por defecto de todas las impresoras del negocio; un terminal con un ancho propio distinto de 48 (Administración → Terminales) lo pisa.</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Ancho del papel</label>
                        <select wire:model="ticket_ancho" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900">
                            <option value="32">58 mm — 32 columnas</option>
                            <option value="42">70 mm — 42 columnas</option>
                            <option value="48">80 mm — 48 columnas</option>
                        </select>
                        @error('ticket_ancho') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">Avance antes del corte (líneas)</label>
                        <input type="number" min="0" max="10" wire:model="ticket_feed" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        @error('ticket_feed') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Mensaje al pie</label>
                        <input type="text" maxlength="120" wire:model="ticket_pie" placeholder="¡Gracias por su compra!" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        @error('ticket_pie') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm text-gray-600">Logo (PNG o JPG — se imprime en blanco y negro)</label>
                        <input type="file" wire:model="ticket_logo" accept="image/png,image/jpeg" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-violet-700 hover:file:bg-violet-100">
                        @error('ticket_logo') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        <p wire:loading wire:target="ticket_logo" class="mt-1 text-xs text-gray-400">Procesando imagen…</p>

                        @if ($ticket_logo)
                            <img src="{{ $ticket_logo->temporaryUrl() }}" class="mt-2 max-h-24 border border-gray-200 bg-white p-2">
                        @elseif ($ticket_logo_preview)
                            <div class="mt-2">
                                <img src="{{ Storage::url($ticket_logo_preview) }}" class="max-h-24 border border-gray-200 bg-white p-2">
                                <button type="button" wire:click="quitarLogo" class="mt-1 block text-xs text-red-600 hover:underline">Quitar logo</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">Guardar configuración</button>
        </form>
    </div>
</div>
