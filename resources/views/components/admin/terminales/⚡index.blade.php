<?php

use App\Models\CashRegister;
use App\Models\Terminal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $ip_address = '';

    public string $printer_name = '';

    public ?int $printer_port = 9100;

    public string $connection_type = 'red';

    public string $usb_path = '';

    public ?int $paper_width_chars = 48;

    public ?int $cash_register_id = null;

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $terminal = Terminal::query()->where('business_id', Auth::user()->businessId())->findOrFail($id);

        $this->editingId = $terminal->id;
        $this->name = $terminal->name;
        $this->code = $terminal->code;
        $this->ip_address = (string) $terminal->ip_address;
        $this->printer_name = (string) $terminal->printer_name;
        $this->printer_port = $terminal->printer_port;
        $this->connection_type = $terminal->connection_type;
        $this->usb_path = (string) $terminal->usb_path;
        $this->paper_width_chars = $terminal->paper_width_chars;
        $this->cash_register_id = $terminal->cash_register_id;
        $this->is_active = $terminal->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'printer_name' => 'nullable|string|max:255',
            'printer_port' => 'nullable|integer|min:1|max:65535',
            'connection_type' => 'required|in:red,usb',
            'usb_path' => 'nullable|string|max:255',
            'paper_width_chars' => 'required|integer|min:24|max:64',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
            'is_active' => 'boolean',
        ]);

        $data['ip_address'] = $data['ip_address'] !== '' ? $data['ip_address'] : null;
        $data['printer_name'] = $data['printer_name'] !== '' ? $data['printer_name'] : null;
        $data['usb_path'] = ($data['usb_path'] ?? '') !== '' ? $data['usb_path'] : null;
        $data['business_id'] = $businessId;
        $data['branch_id'] = Auth::user()->branch_id;

        if ($this->editingId) {
            Terminal::query()->where('business_id', $businessId)->findOrFail($this->editingId)->update($data);
        } else {
            $data['api_token'] = Str::random(48);
            Terminal::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Terminal::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->delete();
    }

    public function regenerateToken(int $id): void
    {
        Terminal::query()->where('business_id', Auth::user()->businessId())->findOrFail($id)->update(['api_token' => Str::random(48)]);
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'code', 'ip_address', 'printer_name', 'usb_path', 'cash_register_id']);
        $this->printer_port = 9100;
        $this->connection_type = 'red';
        $this->paper_width_chars = 48;
        $this->is_active = true;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'terminals' => Terminal::query()
                ->where('business_id', $businessId)
                ->with('cashRegister')
                ->orderBy('name')
                ->get(),
            'cashRegisters' => CashRegister::query()->where('business_id', $businessId)->orderBy('name')->get(),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Terminales</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                Nueva terminal
            </button>
        </div>

        @if ($showForm)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Caja 01" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Código</label>
                            <input type="text" wire:model="code" placeholder="caja-01" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('code') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">IP (opcional)</label>
                            <input type="text" wire:model="ip_address" placeholder="192.168.1.50" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('ip_address') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Caja asociada</label>
                            <select wire:model="cash_register_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="">Sin caja</option>
                                @foreach ($cashRegisters as $register)
                                    <option value="{{ $register->id }}">{{ $register->name }}</option>
                                @endforeach
                            </select>
                            @error('cash_register_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Impresora (opcional)</label>
                            <input type="text" wire:model="printer_name" placeholder="Epson TM-T20 USB" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Tipo de conexión</label>
                            <select wire:model="connection_type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="red">Red (Ethernet/WiFi)</option>
                                <option value="usb">USB</option>
                            </select>
                            @error('connection_type') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Puerto de impresora</label>
                            <input type="number" wire:model="printer_port" placeholder="9100" min="1" max="65535" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('printer_port') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Ruta USB (solo si la conexión es USB)</label>
                            <input type="text" wire:model="usb_path" placeholder="COM3" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('usb_path') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Ancho de papel (caracteres)</label>
                            <input type="number" wire:model="paper_width_chars" placeholder="48" min="24" max="64" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('paper_width_chars') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
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
                        <th class="px-4 py-3">Caja</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Token del agente</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($terminals as $terminal)
                        <tr>
                            <td class="px-4 py-3">{{ $terminal->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $terminal->code }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $terminal->cashRegister?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $terminal->ip_address ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-400">
                                {{ $terminal->api_token ? Str::limit($terminal->api_token, 12, '…') : '—' }}
                                <button wire:click="regenerateToken({{ $terminal->id }})" wire:confirm="¿Regenerar el token? El agente local deberá actualizarse." class="ml-2 text-violet-600 hover:text-violet-600">Regenerar</button>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $terminal->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $terminal->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $terminal->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                <button wire:click="delete({{ $terminal->id }})" wire:confirm="¿Eliminar esta terminal?" class="ml-3 text-red-600 hover:text-red-700">Eliminar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-400">Sin terminales todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
