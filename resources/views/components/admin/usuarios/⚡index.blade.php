<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $branch_id = null;

    public string $role = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = $this->scopedUsers()->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->branch_id = $user->branch_id;
        $this->role = $user->getRoleNames()->first() ?? '';
        $this->is_active = $user->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $businessId = Auth::user()->businessId();

        $assignableRoles = collect(RoleName::cases())
            ->filter(fn (RoleName $role) => $role !== RoleName::SuperAdmin)
            ->map(fn (RoleName $role) => $role->value)
            ->implode(',');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$this->editingId,
            'password' => ($this->editingId ? 'nullable' : 'required').'|string|min:8|confirmed',
            'branch_id' => 'required|exists:branches,id',
            'role' => 'required|in:'.$assignableRoles,
            'is_active' => 'boolean',
        ]);

        $branch = Branch::query()->where('business_id', $businessId)->findOrFail($data['branch_id']);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'branch_id' => $branch->id,
            'is_active' => $data['is_active'],
        ];

        if ($data['password'] !== '' && $data['password'] !== null) {
            $payload['password'] = $data['password'];
        }

        if ($this->editingId) {
            $user = $this->scopedUsers()->findOrFail($this->editingId);
            $user->update($payload);
        } else {
            $user = User::create($payload);
        }

        $user->syncRoles([$data['role']]);

        $this->resetForm();
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $user = $this->scopedUsers()->findOrFail($id);

        abort_if($user->id === Auth::id(), 403, 'No puedes desactivar tu propia cuenta.');

        $user->update(['is_active' => ! $user->is_active]);
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function scopedUsers()
    {
        return User::query()->whereHas('branch', fn ($q) => $q->where('business_id', Auth::user()->businessId()));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'password_confirmation', 'branch_id', 'role']);
        $this->is_active = true;
    }

    public function with(): array
    {
        $businessId = Auth::user()->businessId();

        return [
            'users' => $this->scopedUsers()->with(['branch', 'roles'])->orderBy('name')->get(),
            'branches' => Branch::query()->where('business_id', $businessId)->orderBy('name')->get(),
            'assignableRoles' => collect(RoleName::cases())->filter(fn (RoleName $role) => $role !== RoleName::SuperAdmin),
        ];
    }
};
?>

<div >
    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-900">&larr; Dashboard</a>
                <h1 class="mt-1 text-2xl font-semibold">Usuarios</h1>
            </div>
            <button wire:click="create" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium hover:bg-violet-700 text-white">
                Nuevo usuario
            </button>
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
                            <label class="mb-1 block text-sm text-gray-600">Correo</label>
                            <input type="email" wire:model="email" autocomplete="off" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('email') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Sucursal</label>
                            <select wire:model="branch_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="">Selecciona...</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Rol</label>
                            <select wire:model="role" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                                <option value="">Selecciona...</option>
                                @foreach ($assignableRoles as $roleCase)
                                    <option value="{{ $roleCase->value }}">{{ $roleCase->label() }}</option>
                                @endforeach
                            </select>
                            @error('role') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Contraseña {{ $editingId ? '(dejar vacío para no cambiar)' : '' }}</label>
                            <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                            @error('password') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">Confirmar contraseña</label>
                            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:border-violet-500 focus:outline-none">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 bg-white">
                        Activo
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
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Sucursal</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->roles->pluck('name')->join(', ') ?: 'sin rol' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-gray-500' }}">
                                    {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="edit({{ $user->id }})" class="text-violet-600 hover:text-violet-600">Editar</button>
                                @if ($user->id !== auth()->id())
                                    <button wire:click="toggleActive({{ $user->id }})" wire:confirm="¿{{ $user->is_active ? 'Desactivar' : 'Reactivar' }} a este usuario?" class="ml-3 {{ $user->is_active ? 'text-red-600 hover:text-red-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $user->is_active ? 'Desactivar' : 'Reactivar' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin usuarios todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
