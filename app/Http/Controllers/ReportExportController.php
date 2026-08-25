<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $businessId = Auth::user()->businessId();
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $orders = Order::query()
            ->where('business_id', $businessId)
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [$from, $to])
            ->with(['user', 'customer', 'payments'])
            ->orderBy('completed_at')
            ->get();

        $filename = 'ventas_'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Folio', 'Fecha', 'Cajero', 'Cliente', 'Subtotal', 'Descuento', 'IVA', 'Propina', 'Total', 'Metodos de pago']);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->folio,
                    $order->completed_at?->format('Y-m-d H:i:s'),
                    $order->user->name,
                    $order->customer?->name ?? '',
                    number_format((float) $order->subtotal, 2, '.', ''),
                    number_format((float) $order->discount_amount, 2, '.', ''),
                    number_format((float) $order->tax_amount, 2, '.', ''),
                    number_format((float) $order->tip_amount, 2, '.', ''),
                    number_format((float) $order->total, 2, '.', ''),
                    $order->payments->map(fn ($payment) => $payment->method->label())->unique()->implode(' + '),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
