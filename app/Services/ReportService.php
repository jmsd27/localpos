<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
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

    /**
     * Foto del inventario actual: existencia, mínimo/máximo, valorización
     * (cuando el insumo tiene costo unitario cargado) y bandera de bajo
     * stock por insumo, más los totales del negocio.
     */
    public function inventorySnapshot(int $businessId): array
    {
        $ingredients = Ingredient::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->map(function (Ingredient $ingredient) {
                $stock = (float) $ingredient->stock;
                $minStock = $ingredient->min_stock !== null ? (float) $ingredient->min_stock : null;
                $costPerUnit = $ingredient->cost_per_unit !== null ? (float) $ingredient->cost_per_unit : null;

                return (object) [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit->label(),
                    'stock' => $stock,
                    'min_stock' => $minStock,
                    'max_stock' => $ingredient->max_stock !== null ? (float) $ingredient->max_stock : null,
                    'cost_per_unit' => $costPerUnit,
                    'value' => $costPerUnit !== null ? round($stock * $costPerUnit, 2) : null,
                    'is_low' => $minStock !== null && $stock <= $minStock,
                    'is_active' => (bool) $ingredient->is_active,
                ];
            });

        return [
            'ingredients' => $ingredients,
            'total_ingredients' => $ingredients->count(),
            'low_stock_count' => $ingredients->where('is_low', true)->count(),
            'total_value' => round($ingredients->pluck('value')->filter(fn ($v) => $v !== null)->sum(), 2),
        ];
    }

    /**
     * Movimientos de inventario agrupados por insumo en un rango de fechas:
     * cuánto entró, cuánto salió y el neto — para ver qué se consumió/repuso
     * sin tener que leer el Kardex movimiento por movimiento.
     */
    public function inventoryMovementsSummary(int $businessId, Carbon $from, Carbon $to): array
    {
        $rows = InventoryMovement::query()
            ->where('inventory_movements.business_id', $businessId)
            ->whereBetween('inventory_movements.created_at', [$from, $to])
            ->join('ingredients', 'ingredients.id', '=', 'inventory_movements.ingredient_id')
            ->selectRaw('
                ingredients.id as ingredient_id,
                ingredients.name as ingredient_name,
                ingredients.unit as ingredient_unit,
                COALESCE(SUM(CASE WHEN inventory_movements.quantity > 0 THEN inventory_movements.quantity ELSE 0 END), 0) as entradas,
                COALESCE(SUM(CASE WHEN inventory_movements.quantity < 0 THEN -inventory_movements.quantity ELSE 0 END), 0) as salidas,
                COALESCE(SUM(inventory_movements.quantity), 0) as neto,
                COUNT(*) as movimientos
            ')
            ->groupBy('ingredients.id', 'ingredients.name', 'ingredients.unit')
            ->orderBy('ingredients.name')
            ->get();

        return [
            'rows' => $rows,
            'total_entradas' => round((float) $rows->sum('entradas'), 3),
            'total_salidas' => round((float) $rows->sum('salidas'), 3),
        ];
    }
}
