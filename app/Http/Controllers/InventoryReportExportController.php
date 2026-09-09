<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportExportController extends Controller
{
    public function stock(ReportService $reports): StreamedResponse
    {
        $businessId = Auth::user()->businessId();
        $snapshot = $reports->inventorySnapshot($businessId);

        return response()->streamDownload(function () use ($snapshot) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Insumo', 'Unidad', 'Existencia', 'Minimo', 'Maximo', 'Costo unitario', 'Valor', 'Bajo stock', 'Activo']);

            foreach ($snapshot['ingredients'] as $row) {
                fputcsv($handle, [
                    $row->name,
                    $row->unit,
                    number_format($row->stock, 3, '.', ''),
                    $row->min_stock !== null ? number_format($row->min_stock, 3, '.', '') : '',
                    $row->max_stock !== null ? number_format($row->max_stock, 3, '.', '') : '',
                    $row->cost_per_unit !== null ? number_format($row->cost_per_unit, 4, '.', '') : '',
                    $row->value !== null ? number_format($row->value, 2, '.', '') : '',
                    $row->is_low ? 'Si' : 'No',
                    $row->is_active ? 'Si' : 'No',
                ]);
            }

            fclose($handle);
        }, 'inventario_existencias_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function movements(Request $request, ReportService $reports): StreamedResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $businessId = Auth::user()->businessId();
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $summary = $reports->inventoryMovementsSummary($businessId, $from, $to);
        $filename = 'inventario_movimientos_'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Insumo', 'Unidad', 'Entradas', 'Salidas', 'Neto', 'Movimientos']);

            foreach ($summary['rows'] as $row) {
                fputcsv($handle, [
                    $row->ingredient_name,
                    $row->ingredient_unit,
                    number_format((float) $row->entradas, 3, '.', ''),
                    number_format((float) $row->salidas, 3, '.', ''),
                    number_format((float) $row->neto, 3, '.', ''),
                    (int) $row->movimientos,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
