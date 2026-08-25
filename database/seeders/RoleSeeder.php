<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            RoleName::SuperAdmin->value => ['*'],
            RoleName::Administrador->value => [
                'ventas.crear', 'ventas.ver', 'ventas.editar', 'ventas.anular', 'ventas.aplicar_descuento',
                'caja.abrir', 'caja.cerrar', 'caja.ver_movimientos', 'caja.registrar_movimiento',
                'inventario.ver', 'inventario.ajustar', 'inventario.ver_kardex',
                'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.ver',
                'clientes.crear', 'clientes.editar', 'clientes.eliminar', 'clientes.ver',
                'compras.crear', 'compras.aprobar', 'compras.ver',
                'cocina.ver', 'cocina.gestionar',
                'reportes.ver', 'reportes.exportar',
                'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.asignar_rol',
                'configuracion.editar', 'configuracion.ver',
            ],
            RoleName::Encargado->value => [
                'ventas.ver', 'ventas.editar', 'ventas.anular', 'ventas.aplicar_descuento',
                'caja.ver_movimientos', 'inventario.ver', 'inventario.ajustar', 'inventario.ver_kardex',
                'productos.ver', 'clientes.crear', 'clientes.editar', 'clientes.ver',
                'compras.ver', 'cocina.ver', 'cocina.gestionar', 'reportes.ver',
            ],
            RoleName::Cajero->value => [
                'ventas.crear', 'ventas.ver', 'ventas.aplicar_descuento',
                'caja.abrir', 'caja.cerrar', 'caja.registrar_movimiento',
                'clientes.crear', 'clientes.ver',
            ],
            RoleName::Mesero->value => [
                'ventas.crear', 'ventas.ver', 'clientes.crear', 'clientes.ver',
            ],
            RoleName::Cocina->value => [
                'cocina.ver', 'cocina.gestionar',
            ],
            RoleName::Barra->value => [
                'cocina.ver', 'cocina.gestionar',
            ],
            RoleName::Inventarios->value => [
                'inventario.ver', 'inventario.ajustar', 'inventario.ver_kardex',
                'compras.crear', 'compras.aprobar', 'compras.ver',
            ],
            RoleName::Reportes->value => [
                'reportes.ver', 'reportes.exportar',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::findOrCreate($name);

            if ($permissions === ['*']) {
                continue;
            }

            $role->syncPermissions($permissions);
        }
    }
}
