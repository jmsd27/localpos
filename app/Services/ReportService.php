<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class ReportService
{
    /**
     * Resumen de ventas completadas en un rango de fechas: totales,
     * desglose por método de pago, top de productos y ventas por cajero.
     */
    public function salesSummary(int $businessId, Carbon $from, Carbon $to): array
    {
        $orders = Order::query()
            ->where('business_id', $businessId)
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [$from, $to]);

        $totals = (clone $orders)->selectRaw('
            COUNT(*) as orders_count,
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(discount_amount), 0) as discount_amount,
            COALESCE(SUM(tax_amount), 0) as tax_amount,
            COALESCE(SUM(tip_amount), 0) as tip_amount,
            COALESCE(SUM(total), 0) as total
        ')->first();

        $orderIds = (clone $orders)->pluck('id');

        $byPaymentMethod = Payment::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->toBase()
            ->pluck('total', 'method');

        $topProducts = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('product_id, name, SUM(quantity) as quantity, SUM(subtotal) as total')
            ->groupBy('product_id', 'name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $byUser = (clone $orders)
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw('users.name as user_name, COUNT(*) as orders_count, SUM(orders.total) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();

        return [
            'orders_count' => (int) $totals->orders_count,
            'subtotal' => (float) $totals->subtotal,
            'discount_amount' => (float) $totals->discount_amount,
            'tax_amount' => (float) $totals->tax_amount,
            'tip_amount' => (float) $totals->tip_amount,
            'total' => (float) $totals->total,
            'by_payment_method' => $byPaymentMethod,
            'top_products' => $topProducts,
            'by_user' => $byUser,
        ];
    }
}
