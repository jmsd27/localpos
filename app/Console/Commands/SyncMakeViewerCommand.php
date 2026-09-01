<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Crea una cuenta de acceso directamente en la instancia "mirror", para que
 * el dueño/gerente pueda entrar a ver el espejo remotamente. No usar las
 * cuentas sincronizadas desde las sucursales: su password se reemplaza a
 * propósito por un valor aleatorio en cada sync (ver SyncModelResolver), así
 * que nunca son utilizables para iniciar sesión — la cuenta de visualización
 * vive solo en la nube.
 */
class SyncMakeViewerCommand extends Command
{
    protected $signature = 'sync:make-viewer {name} {email} {password}';

    protected $description = 'Crea una cuenta de solo lectura en la instancia mirror para ver el espejo remotamente.';

    public function handle(): int
    {
        if (config('sync.role') !== 'mirror') {
            $this->error('Este comando solo debe correrse en la instancia "mirror" (SYNC_ROLE=mirror).');

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
            'is_active' => true,
        ]);

        $this->info("Cuenta de visualización creada: {$user->email}. Puede iniciar sesión en el espejo; solo tendrá acceso de lectura (reportes, ventas, etc.), sin importar su rol.");

        return self::SUCCESS;
    }
}
