<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Representative permission set for Fase 1 (spec sección 6).
     * Cada fase posterior añade más permisos dentro de estos namespaces.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'ventas.crear', 'ventas.ver', 'ventas.editar', 'ventas.anular', 'ventas.aplicar_descuento',
        'caja.abrir', 'caja.cerrar', 'caja.ver_movimientos', 'caja.registrar_movimiento',
        'inventario.ver', 'inventario.ajustar', 'inventario.ver_kardex',
        'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.ver',
        'clientes.crear', 'clientes.editar', 'clientes.eliminar', 'clientes.ver',
        'compras.crear', 'compras.aprobar', 'compras.ver',
        'reportes.ver', 'reportes.exportar',
        'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.asignar_rol',
        'configuracion.editar', 'configuracion.ver',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }
    }
}
