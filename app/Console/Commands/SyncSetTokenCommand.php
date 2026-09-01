<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;

/**
 * Se corre del lado LOCAL, una vez por sucursal, para guardar el token que
 * sync:push usará al hablar con la nube (mismo token generado por
 * sync:provision-branch del lado mirror). No existe pantalla de
 * administración de sucursales todavía, de ahí el comando.
 */
class SyncSetTokenCommand extends Command
{
    protected $signature = 'sync:set-token {code : Código de la sucursal local, ej. MTY-01} {token : Token generado por sync:provision-branch en la nube}';

    protected $description = 'Guarda el sync_token de esta sucursal local, usado por sync:push para autenticarse contra la nube.';

    public function handle(): int
    {
        $branch = Branch::query()->where('code', $this->argument('code'))->first();

        if (! $branch) {
            $this->error("No existe una sucursal local con code={$this->argument('code')}.");

            return self::FAILURE;
        }

        $branch->update(['sync_token' => $this->argument('token')]);

        $this->info("Token de sync guardado para la sucursal {$branch->code}.");

        return self::SUCCESS;
    }
}
