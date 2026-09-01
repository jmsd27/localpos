<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Business;
use App\Models\SyncIdMap;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Se corre UNA VEZ, del lado de la instancia "mirror" en la nube, al dar de
 * alta cada sucursal nueva — antes de su primer sync:push local. Resuelve el
 * problema de arranque: la fila de Business/Branch en la nube es en sí misma
 * un dato sincronizado, pero AuthenticateSyncToken necesita encontrarla desde
 * el primer request. Ver plan: hazy-weaving-wave.md, sección 2.2.
 */
class SyncProvisionBranchCommand extends Command
{
    protected $signature = 'sync:provision-branch
        {code : Código único de la sucursal, ej. MTY-01}
        {local-business-id : id de la fila businesses en la instalación local de esa sucursal}
        {local-branch-id : id de la fila branches en la instalación local de esa sucursal}
        {--business-name= : Nombre del negocio, si aún no existe en la nube}
        {--branch-name= : Nombre de la sucursal}
        {--token= : Token de sync a usar; si se omite se genera uno aleatorio}';

    protected $description = 'Prepara en la nube (SYNC_ROLE=mirror) el Business/Branch placeholder de una sucursal nueva antes de su primer sync:push.';

    public function handle(): int
    {
        if (config('sync.role') !== 'mirror') {
            $this->error('Este comando solo debe correrse en la instancia "mirror" (SYNC_ROLE=mirror).');

            return self::FAILURE;
        }

        $code = $this->argument('code');
        $localBusinessId = (int) $this->argument('local-business-id');
        $localBranchId = (int) $this->argument('local-branch-id');
        $token = $this->option('token') ?: Str::random(40);

        if (Branch::query()->where('code', $code)->exists()) {
            $this->error("Ya existe una sucursal con code={$code} en la nube.");

            return self::FAILURE;
        }

        $businessMap = SyncIdMap::query()
            ->where('branch_code', $code)
            ->where('model_type', 'business')
            ->where('local_id', $localBusinessId)
            ->first();

        $business = $businessMap
            ? Business::query()->find($businessMap->cloud_id)
            : Business::query()->create(['name' => $this->option('business-name') ?: "Negocio {$code}"]);

        if (! $businessMap) {
            SyncIdMap::query()->create([
                'branch_code' => $code,
                'model_type' => 'business',
                'local_id' => $localBusinessId,
                'cloud_id' => $business->id,
            ]);
        }

        $branch = Branch::query()->create([
            'business_id' => $business->id,
            'name' => $this->option('branch-name') ?: $code,
            'code' => $code,
            'sync_token' => $token,
        ]);

        SyncIdMap::query()->create([
            'branch_code' => $code,
            'model_type' => 'branch',
            'local_id' => $localBranchId,
            'cloud_id' => $branch->id,
        ]);

        $this->info("Sucursal {$code} preparada en la nube. En la instalación local corre: php artisan sync:set-token {$code} {$token}");

        return self::SUCCESS;
    }
}
