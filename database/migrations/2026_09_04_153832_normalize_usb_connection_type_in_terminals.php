<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `connection_type` distinguía solo 'red' | 'usb'. Ahora que el agente local
 * soporta dos formas distintas de USB (puerto COM serie vs. impresora
 * instalada/compartida en Windows), 'usb' se vuelve ambiguo — se reemplaza
 * por 'usb_serial' (comportamiento idéntico al 'usb' anterior) y se agrega
 * 'usb_impresora' como valor nuevo. Migración de datos, no de esquema: la
 * columna sigue siendo un string libre, sin constraint a nivel de BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('terminals')->where('connection_type', 'usb')->update(['connection_type' => 'usb_serial']);
    }

    public function down(): void
    {
        DB::table('terminals')->where('connection_type', 'usb_serial')->update(['connection_type' => 'usb']);
    }
};
