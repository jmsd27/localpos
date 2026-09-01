<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * No usa WithoutModelEvents: spatie/laravel-permission invalida su caché
     * de permisos escuchando eventos de modelo (created/updated/deleted); si
     * se suprimen esos eventos, RoleSeeder falla al no encontrar los permisos
     * que PermissionSeeder acaba de crear en la misma ejecución.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SuperAdminSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
