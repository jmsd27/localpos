<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OfflineComandaController extends Controller
{
    /**
     * Catálogo mínimo para que el navegador pueda armar comandas sin
     * conexión: productos vendibles/activos con sus modificadores, y el
     * estado actual de las mesas. Se pide una vez al cargar mesas/mapa o
     * mesas/comanda y se refresca solo cada pocos minutos (ver
     * resources/js/offline-comanda.js).
     */
    public function catalogo(): JsonResponse
    {
        $businessId = Auth::user()->businessId();

        $products = Product::query()
            ->where('business_id', $businessId)
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->with('modifierGroups.options')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'tax_rate' => (float) $product->tax_rate,
                'product_category_id' => $product->product_category_id,
                'kitchen_station_id' => $product->kitchen_station_id,
                'modifier_groups' => $product->modifierGroups->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'min_selections' => $group->min_selections,
                    'max_selections' => $group->max_selections,
                    'options' => $group->options->map(fn ($option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => (float) $option->price_delta,
                    ]),
                ]),
            ]);

        $categories = ProductCategory::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tables = Table::query()
            ->where('business_id', $businessId)
            ->get(['id', 'name', 'status']);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'products' => $products,
            'categories' => $categories,
            'tables' => $tables,
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
