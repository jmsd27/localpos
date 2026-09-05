<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Table;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function sincronizar(Request $request, Table $table, SaleService $sales): JsonResponse
    {
        $user = Auth::user();
        abort_unless($table->business_id === $user->businessId(), 404);

        $data = $request->validate([
            'client_order_uuid' => ['nullable', 'string'],
            'existing_order_id' => ['nullable', 'integer'],
            'people_count' => ['nullable', 'integer', 'min:1'],
            'requested_bill' => ['boolean'],
            'items' => ['array'],
            'items.*.client_item_uuid' => ['required', 'string'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.modifiers' => ['array'],
            'items.*.modifiers.*.modifier_option_id' => ['nullable', 'integer'],
            'items.*.modifiers.*.name' => ['required', 'string'],
            'items.*.modifiers.*.price_delta' => ['required', 'numeric'],
        ]);

        $products = Product::query()
            ->whereIn('id', collect($data['items'] ?? [])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($data, $table, $user, $sales, $products) {
            $order = $this->resolveOrder($data, $table, $user, $sales);

            $newItems = collect($data['items'] ?? [])
                ->reject(fn ($item) => $order->items()->where('client_uuid', $item['client_item_uuid'])->exists())
                ->map(function ($item) use ($products) {
                    $product = $products->get($item['product_id']);

                    return [
                        'product_id' => $item['product_id'],
                        'client_uuid' => $item['client_item_uuid'],
                        'kitchen_station_id' => $product?->kitchen_station_id,
                        'name' => $product?->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => (float) $product?->price,
                        'tax_rate' => (float) $product?->tax_rate,
                        'notes' => $item['notes'] ?? null,
                        'modifiers' => $item['modifiers'] ?? [],
                    ];
                })
                ->values()
                ->all();

            if ($newItems !== []) {
                $sales->addItemsToOrder($order, $newItems);
            }

            if ($data['requested_bill'] ?? false) {
                $sales->requestBill($order);
            }

            return response()->json(['order' => $order->fresh(['items.modifiers'])]);
        });
    }

    private function resolveOrder(array $data, Table $table, $user, SaleService $sales): Order
    {
        if (! empty($data['existing_order_id'])) {
            $order = Order::query()
                ->where('id', $data['existing_order_id'])
                ->where('table_id', $table->id)
                ->where('business_id', $table->business_id)
                ->first();

            if (! $order || $order->status !== OrderStatus::Pending) {
                abort(409, 'La mesa ya no tiene una comanda abierta para sincronizar.');
            }

            return $order;
        }

        if (! empty($data['client_order_uuid'])) {
            $existing = Order::query()
                ->where('business_id', $table->business_id)
                ->where('client_uuid', $data['client_order_uuid'])
                ->first();

            if ($existing) {
                return $existing;
            }

            return $sales->createDraftOrder([
                'business_id' => $table->business_id,
                'branch_id' => $table->branch_id,
                'terminal_id' => session('terminal_id'),
                'cash_register_session_id' => session('cash_register_session_id'),
                'user_id' => $user->id,
                'table_id' => $table->id,
                'people_count' => $data['people_count'] ?? null,
                'order_type' => 'mesa',
                'client_uuid' => $data['client_order_uuid'],
            ]);
        }

        abort(422, 'Falta client_order_uuid o existing_order_id.');
    }
}
