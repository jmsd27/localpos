<?php

use App\Enums\CashRegisterSessionStatus;
use App\Enums\OrderStatus;
use App\Models\CashRegisterSession;
use App\Models\Ingredient;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function logout(): void
    {
        Auth::logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirectRoute('login', navigate: true);
    }

    public function with(): array
    {
        $user = Auth::user();
        $businessId = $user->businessId();
        $kpis = [];

        if ($user->can('ventas.ver')) {
            $today = Order::query()
                ->where('business_id', $businessId)
                ->where('status', OrderStatus::Completed)
                ->whereDate('completed_at', now())
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                ->first();

            $kpis['sales_today_count'] = (int) $today->count;
            $kpis['sales_today_total'] = (float) $today->total;
        }

        if ($user->can('caja.ver_movimientos')) {
            $kpis['open_sessions'] = CashRegisterSession::query()
                ->whereHas('cashRegister', fn ($q) => $q->where('business_id', $businessId))
                ->where('status', CashRegisterSessionStatus::Open)
                ->count();
        }

        if ($user->can('inventario.ver')) {
            $kpis['low_stock'] = Ingredient::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->whereNotNull('min_stock')
                ->whereColumn('stock', '<', 'min_stock')
                ->count();
        }

        return ['kpis' => $kpis];
    }
};
?>

<div >
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">LOCALPOS</h1>
            <button wire:click="logout" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-white">
                Cerrar sesión
            </button>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <p class="text-gray-600">
                Sesión iniciada como <span class="font-medium text-gray-900">{{ Auth::user()->name }}</span>
                ({{ Auth::user()->email }}).
            </p>
            <p class="mt-2 text-sm text-gray-500">
                Roles: {{ Auth::user()->getRoleNames()->join(', ') ?: 'sin rol asignado' }}
            </p>
        </div>

        @if (! empty($kpis))
            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                @if (isset($kpis['sales_today_count']))
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <p class="text-xs text-gray-500">Ventas de hoy</p>
                        <p class="mt-1 text-xl font-semibold">{{ $kpis['sales_today_count'] }} &middot; ${{ number_format($kpis['sales_today_total'], 2) }}</p>
                    </div>
                @endif
                @if (isset($kpis['open_sessions']))
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <p class="text-xs text-gray-500">Cajas abiertas</p>
                        <p class="mt-1 text-xl font-semibold">{{ $kpis['open_sessions'] }}</p>
                    </div>
                @endif
                @if (isset($kpis['low_stock']))
                    <div class="rounded-xl border {{ $kpis['low_stock'] > 0 ? 'border-amber-700 bg-amber-50' : 'border-gray-200 bg-white' }} p-4">
                        <p class="text-xs text-gray-500">Insumos bajo mínimo</p>
                        <p class="mt-1 text-xl font-semibold">{{ $kpis['low_stock'] }}</p>
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @can('ventas.crear')
                <a href="{{ route('pos') }}" wire:navigate class="rounded-xl border border-violet-200 bg-violet-600/20 p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Punto de venta</span>
                </a>
                <a href="{{ route('mesas.mapa') }}" wire:navigate class="rounded-xl border border-violet-200 bg-violet-600/20 p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Mapa de mesas</span>
                </a>
            @endcan
            @can('ventas.ver')
                <a href="{{ route('ventas.index') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Ventas</span>
                </a>
            @endcan
            @can('cocina.ver')
                <a href="{{ route('kds') }}" wire:navigate class="rounded-xl border border-violet-200 bg-violet-600/20 p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Cocina (KDS)</span>
                </a>
            @endcan
            @can('productos.ver')
                <a href="{{ route('admin.categorias') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Categorías</span>
                </a>
                <a href="{{ route('admin.productos') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Productos</span>
                </a>
                <a href="{{ route('admin.modificadores') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Modificadores</span>
                </a>
                <a href="{{ route('admin.menus-qr') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Menús QR</span>
                </a>
            @endcan
            @can('clientes.ver')
                <a href="{{ route('admin.clientes') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Clientes</span>
                </a>
            @endcan
            @can('inventario.ajustar')
                <a href="{{ route('admin.insumos') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Insumos</span>
                </a>
                <a href="{{ route('admin.recetas') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Recetas</span>
                </a>
                <a href="{{ route('inventario.movimientos') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Inventario</span>
                </a>
            @endcan
            @can('compras.ver')
                <a href="{{ route('compras.index') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Compras</span>
                </a>
            @endcan
            @can('compras.crear')
                <a href="{{ route('admin.proveedores') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Proveedores</span>
                </a>
            @endcan
            @can('reportes.ver')
                <a href="{{ route('reportes.index') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Reportes</span>
                </a>
            @endcan
            @can('configuracion.ver')
                <a href="{{ route('admin.auditoria') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Auditoría</span>
                </a>
            @endcan
            @can('usuarios.crear')
                <a href="{{ route('admin.usuarios') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Usuarios</span>
                </a>
            @endcan
            @can('configuracion.editar')
                <a href="{{ route('admin.configuracion') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Configuración</span>
                </a>
                <a href="{{ route('admin.terminales') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Terminales</span>
                </a>
                <a href="{{ route('admin.cajas') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Cajas</span>
                </a>
                <a href="{{ route('admin.salones') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Salones</span>
                </a>
                <a href="{{ route('admin.mesas') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Mesas</span>
                </a>
                <a href="{{ route('admin.estaciones') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Estaciones</span>
                </a>
                <a href="{{ route('impresion.cola') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Cola de impresión</span>
                </a>
                <a href="{{ route('admin.backups') }}" wire:navigate class="rounded-xl border border-gray-200 bg-white p-4 text-center hover:border-violet-500">
                    <span class="block text-sm font-medium">Respaldos</span>
                </a>
            @endcan
        </div>
    </div>
</div>
