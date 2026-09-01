<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solo se usa del lado "mirror" en la nube. Traduce (branch_code, model_type,
 * local_id) de una instalación local hacia el id autoincremental propio de
 * la nube, para poder reescribir foreign keys al aplicar un lote de sync.
 */
class SyncIdMap extends Model
{
    protected $table = 'sync_id_map';

    protected $fillable = [
        'branch_code',
        'model_type',
        'local_id',
        'cloud_id',
    ];
}
