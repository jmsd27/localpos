<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\BarLaMartinaCatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Carga única de puesta en marcha para Bar La Martina: catálogo real
 * (BarLaMartinaCatalogSeeder) + roles/permisos + el personal de la imagen
 * que pasó el dueño, con contraseñas generadas en el momento (nunca
 * versionadas). Pensado para correr una sola vez sobre una base nueva o
 * casi vacía (p. ej. al pasar el espejo de Vercel de mirror a source) — es
 * seguro reintentarlo, no duplica nada que ya exista por nombre/email.
 *
 * Se dispara sin acceso a SSH/consola vía POST /deploy/importar-datos-reales
 * (routes/web.php), protegido con el mismo DEPLOY_KEY que /deploy/migrate.
 */
class ImportarDatosRealesCommand extends Command
{
    protected $signature = 'barlamartina:importar-datos-reales';

    protected $description = 'Carga el catálogo, roles y personal reales de Bar La Martina (idempotente)';

    /** @var array<int, array{name:string, email:string, roles:list<string>}> */
    private const STAFF = [
        ['name' => 'Marco Guillén', 'email' => 'marco.guillen@barlamartina.local', 'roles' => ['mesero']],
        ['name' => 'Michelle Flores', 'email' => 'michelle.flores@barlamartina.local', 'roles' => ['mesero']],
        ['name' => 'Adriana Soto', 'email' => 'adriana.soto@barlamartina.local', 'roles' => ['mesero']],
        ['name' => 'Jennifer Franco', 'email' => 'jennifer.franco@barlamartina.local', 'roles' => ['mesero', 'auditor']],
        ['name' => 'Donovan Caro', 'email' => 'donovan.caro@barlamartina.local', 'roles' => ['mesero']],
        ['name' => 'Arturo León', 'email' => 'arturo.leon@barlamartina.local', 'roles' => ['director']],
    ];

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => BarLaMartinaCatalogSeeder::class, '--force' => true]);

        $branch = Branch::where('business_id', Business::firstOrFail()->id)->firstOrFail();

        $this->info('Personal:');

        foreach (self::STAFF as $staff) {
            $password = bin2hex(random_bytes(5));

            $user = User::updateOrCreate(
                ['email' => $staff['email']],
                ['name' => $staff['name'], 'branch_id' => $branch->id, 'password' => Hash::make($password), 'is_active' => true],
            );

            $user->syncRoles($staff['roles']);

            $this->line(sprintf('  %-38s %-14s %s', $staff['email'], $password, implode('+', $staff['roles'])));
        }

        $this->warn('Guardá esas contraseñas ahora — no se van a volver a mostrar. Pedile a cada persona que la cambie en su primer ingreso.');

        return self::SUCCESS;
    }
}
