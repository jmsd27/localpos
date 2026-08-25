<?php

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $business_name = '';

    public string $currency = 'MXN';

    public string $timezone = 'America/Mexico_City';

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    public function mount(): void
    {
        abort_if(Business::query()->exists(), 404);
    }

    public function install(): void
    {
        abort_if(Business::query()->exists(), 404);

        $data = $this->validate([
            'business_name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:64',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        $user = DB::transaction(function () use ($data) {
            $business = Business::create([
                'name' => $data['business_name'],
                'currency' => strtoupper($data['currency']),
                'timezone' => $data['timezone'],
            ]);

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => 'Sucursal Principal',
                'code' => 'principal',
                'is_main' => true,
            ]);

            (new PermissionSeeder)->run();
            (new RoleSeeder)->run();

            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

            $user->assignRole(RoleName::SuperAdmin->value);

            return $user;
        });

        Auth::login($user);
        Session::regenerate();

        $this->redirectRoute('dashboard', navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10">
    <div class="w-full max-w-lg rounded-xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
        <h1 class="mb-1 text-center text-2xl font-semibold text-white">LOCALPOS</h1>
        <p class="mb-6 text-center text-sm text-slate-400">Configuración inicial &mdash; solo se hace una vez.</p>

        <form wire:submit="install" class="space-y-4">
            <div>
                <h2 class="mb-2 text-sm font-semibold text-slate-300">Negocio</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-sm text-slate-300">Nombre del negocio</label>
                        <input type="text" wire:model="business_name" placeholder="Mi Restaurante" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        @error('business_name') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Moneda</label>
                        <input type="text" wire:model="currency" maxlength="3" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        @error('currency') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm text-slate-300">Zona horaria</label>
                        <input type="text" wire:model="timezone" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        @error('timezone') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div>
                <h2 class="mb-2 text-sm font-semibold text-slate-300">Cuenta de administrador</h2>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Nombre</label>
                        <input type="text" wire:model="admin_name" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        @error('admin_name') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Correo</label>
                        <input type="email" wire:model="admin_email" autocomplete="username" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        @error('admin_email') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Contraseña</label>
                            <input type="password" wire:model="admin_password" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                            @error('admin_password') <span class="mt-1 block text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Confirmar contraseña</label>
                            <input type="password" wire:model="admin_password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white transition hover:bg-indigo-500 disabled:opacity-50">
                <span wire:loading.remove wire:target="install">Crear negocio y comenzar</span>
                <span wire:loading wire:target="install">Configurando…</span>
            </button>
        </form>
    </div>
</div>
