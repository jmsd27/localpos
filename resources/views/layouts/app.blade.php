<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#7c3aed">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        <meta name="apple-mobile-web-app-capable" content="yes">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased lg:flex" x-data="{ sidebarOpen: false }">

        @if (config('sync.role') === 'mirror')
            <div class="fixed inset-x-0 top-0 z-50 bg-amber-500 px-4 py-1.5 text-center text-sm font-medium text-white">
                Vista de solo lectura — reflejo en la nube. La operación real ocurre en el sistema local.
            </div>
        @endif

        {{-- Overlay móvil --}}
        <div x-show="sidebarOpen" x-transition.opacity x-cloak
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:static lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-100 px-5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-600 text-white">
                    <x-icon name="building-storefront" class="h-5 w-5" />
                </span>
                <span class="text-base font-semibold text-gray-900">{{ config('app.name', 'LOCALPOS') }}</span>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div>
                    <a href="{{ route('dashboard') }}" wire:navigate
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                        <x-icon name="home" class="h-5 w-5" />
                        Dashboard
                    </a>
                </div>

                @canany(['ventas.crear', 'ventas.ver', 'cocina.ver', 'caja.abrir', 'caja.ver_movimientos', 'caja.cerrar'])
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Operación</p>
                        <div class="mt-1 space-y-1">
                            @can('ventas.crear')
                                <a href="{{ route('pos') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('pos', 'pos.terminal') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="shopping-cart" class="h-5 w-5" />
                                    Punto de venta
                                </a>
                                <a href="{{ route('mesas.mapa') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('mesas.mapa', 'mesas.comanda') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="table-cells" class="h-5 w-5" />
                                    Mapa de mesas
                                </a>
                            @endcan
                            @can('ventas.ver')
                                <a href="{{ route('ventas.index') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('ventas.index') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="clipboard-document-list" class="h-5 w-5" />
                                    Ventas
                                </a>
                            @endcan
                            @can('cocina.ver')
                                <a href="{{ route('kds') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('kds') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="fire" class="h-5 w-5" />
                                    Cocina (KDS)
                                </a>
                            @endcan
                            @canany(['caja.abrir', 'caja.ver_movimientos', 'caja.cerrar'])
                                <a href="{{ route('caja.movimientos') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('caja.movimientos', 'caja.apertura', 'caja.cierre') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="banknotes" class="h-5 w-5" />
                                    Caja
                                </a>
                            @endcanany
                            @can('caja.ver_movimientos')
                                <a href="{{ route('caja.historial') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('caja.historial') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="chart-bar" class="h-5 w-5" />
                                    Historial de caja
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @canany(['productos.ver', 'clientes.ver'])
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Catálogo</p>
                        <div class="mt-1 space-y-1">
                            @can('productos.ver')
                                <a href="{{ route('admin.productos') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.productos') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="tag" class="h-5 w-5" />
                                    Productos
                                </a>
                                <a href="{{ route('admin.categorias') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.categorias') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="archive-box" class="h-5 w-5" />
                                    Categorías
                                </a>
                                <a href="{{ route('admin.modificadores') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.modificadores') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="cog-6-tooth" class="h-5 w-5" />
                                    Modificadores
                                </a>
                            @endcan
                            @can('clientes.ver')
                                <a href="{{ route('admin.clientes') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.clientes') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="users" class="h-5 w-5" />
                                    Clientes
                                </a>
                            @endcan
                            @can('productos.ver')
                                <a href="{{ route('admin.menus-qr') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.menus-qr') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="qr-code" class="h-5 w-5" />
                                    Menús QR
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @canany(['inventario.ajustar', 'inventario.ver_kardex'])
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Inventario</p>
                        <div class="mt-1 space-y-1">
                            @can('inventario.ajustar')
                                <a href="{{ route('admin.insumos') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.insumos') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="archive-box" class="h-5 w-5" />
                                    Insumos
                                </a>
                                <a href="{{ route('admin.recetas') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.recetas') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="clipboard-document-list" class="h-5 w-5" />
                                    Recetas
                                </a>
                                <a href="{{ route('inventario.movimientos') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('inventario.movimientos') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="chart-bar" class="h-5 w-5" />
                                    Movimientos
                                </a>
                            @endcan
                            @can('inventario.ver_kardex')
                                <a href="{{ route('inventario.kardex') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('inventario.kardex') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="clipboard-document-list" class="h-5 w-5" />
                                    Kardex
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @canany(['compras.ver', 'compras.crear'])
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Compras</p>
                        <div class="mt-1 space-y-1">
                            @can('compras.ver')
                                <a href="{{ route('compras.index') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('compras.index') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="truck" class="h-5 w-5" />
                                    Compras
                                </a>
                            @endcan
                            @can('compras.crear')
                                <a href="{{ route('admin.proveedores') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.proveedores') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="users" class="h-5 w-5" />
                                    Proveedores
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @can('reportes.ver')
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Reportes</p>
                        <div class="mt-1 space-y-1">
                            <a href="{{ route('reportes.index') }}" wire:navigate
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('reportes.index') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                <x-icon name="chart-bar" class="h-5 w-5" />
                                Reportes
                            </a>
                        </div>
                    </div>
                @endcan

                @canany(['usuarios.crear', 'configuracion.editar', 'configuracion.ver'])
                    <div>
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Administración</p>
                        <div class="mt-1 space-y-1">
                            @can('usuarios.crear')
                                <a href="{{ route('admin.usuarios') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.usuarios') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="users" class="h-5 w-5" />
                                    Usuarios
                                </a>
                            @endcan
                            @can('configuracion.editar')
                                <a href="{{ route('admin.terminales') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.terminales') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="printer" class="h-5 w-5" />
                                    Terminales
                                </a>
                                <a href="{{ route('admin.cajas') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.cajas') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="banknotes" class="h-5 w-5" />
                                    Cajas
                                </a>
                                <a href="{{ route('admin.salones') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.salones') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="building-storefront" class="h-5 w-5" />
                                    Salones
                                </a>
                                <a href="{{ route('admin.mesas') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.mesas') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="table-cells" class="h-5 w-5" />
                                    Mesas
                                </a>
                                <a href="{{ route('admin.estaciones') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.estaciones') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="fire" class="h-5 w-5" />
                                    Estaciones
                                </a>
                                <a href="{{ route('impresion.cola') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('impresion.cola') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="printer" class="h-5 w-5" />
                                    Cola de impresión
                                </a>
                                <a href="{{ route('admin.backups') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.backups') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="cloud-arrow-down" class="h-5 w-5" />
                                    Respaldos
                                </a>
                                <a href="{{ route('admin.configuracion') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.configuracion') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="cog-6-tooth" class="h-5 w-5" />
                                    Configuración
                                </a>
                            @endcan
                            @can('configuracion.ver')
                                <a href="{{ route('admin.auditoria') }}" wire:navigate
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.auditoria') ? 'border-l-2 border-violet-600 bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon name="shield-check" class="h-5 w-5" />
                                    Auditoría
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany
            </nav>
        </aside>

        {{-- Contenido --}}
        <div class="flex min-h-screen w-full flex-1 flex-col lg:min-w-0">
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white/80 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" type="button" class="text-gray-500 hover:text-gray-700 lg:hidden">
                        <x-icon name="bars-3" class="h-6 w-6" />
                    </button>
                    <h1 class="text-base font-semibold text-gray-900 sm:text-lg">{{ $title ?? '' }}</h1>
                </div>

                <livewire:layout.user-menu />
            </header>

            <main class="flex-1 p-4 sm:p-6">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
