<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::query()->firstOrCreate(
            ['name' => 'Mi Negocio'],
            [
                'currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
            ],
        );

        $branch = Branch::query()->firstOrCreate(
            ['business_id' => $business->id, 'code' => 'principal'],
            [
                'name' => 'Sucursal Principal',
                'is_main' => true,
            ],
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@localpos.local'],
            [
                'name' => 'Super Admin',
                'password' => 'localpos-admin',
                'branch_id' => $branch->id,
                'is_active' => true,
            ],
        );

        $admin->assignRole(RoleName::SuperAdmin->value);
    }
}
