<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Plantilla CSV para el importador de insumos de admin.insumos.index:
 * Nombre, Unidad, Cantidad, con dos filas de ejemplo.
 */
class InsumoTemplateController extends Controller
{
    public function __invoke(): Response
    {
        $rows = [
            ['Nombre', 'Unidad', 'Cantidad'],
            ['Cerveza Corona 355ml', 'botella', '48'],
            ['Chile Curtido', 'kg', '5'],
        ];

        $csv = collect($rows)
            ->map(fn (array $row) => implode(',', $row))
            ->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-insumos.csv"',
        ]);
    }
}
