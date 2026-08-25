<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Ajusta el stock de un insumo. $quantity es firmado: positivo para
     * entradas/compras/devoluciones/ajustes al alza, negativo para
     * salidas/mermas/consumos/ajustes a la baja.
     */
    public function adjustStock(
        Ingredient $ingredient,
        InventoryMovementType $type,
        float $quantity,
        ?int $userId = null,
        ?string $reason = null,
        ?Model $reference = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($ingredient, $type, $quantity, $userId, $reason, $reference) {
            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);

            $resulting = round((float) $locked->stock + $quantity, 3);

            if ($resulting < 0) {
                $policy = $this->settings->get($locked->business_id, 'inventario_negativo', 'permitir_alerta');

                if ($policy === 'no_permitir') {
                    throw new InvalidArgumentException("Stock insuficiente de \"{$locked->name}\" para completar la operación.");
                }
            }

            $locked->update(['stock' => $resulting]);

            $movement = InventoryMovement::create([
                'business_id' => $locked->business_id,
                'ingredient_id' => $locked->id,
                'type' => $type,
                'quantity' => $quantity,
                'resulting_stock' => $resulting,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            $this->auditLogger->log('inventario.'.$type->value, $movement, null, $movement->only(['quantity', 'resulting_stock']));

            return $movement;
        });
    }

    /**
     * Descuenta del inventario los insumos de la receta de cada línea vendida
     * en la orden (solo para productos marcados como inventariables).
     */
    public function consumeForOrder(Order $order, int $userId): void
    {
        $items = $order->items()->with('product.recipeItems.ingredient')->get();

        foreach ($items as $item) {
            $this->consumeForOrderItem($item, $userId);
        }
    }

    public function consumeForOrderItem(OrderItem $item, int $userId): void
    {
        $product = $item->product;

        if (! $product || ! $product->is_inventoried) {
            return;
        }

        foreach ($product->recipeItems as $recipeItem) {
            $required = (float) $recipeItem->quantity * (float) $item->quantity;

            $this->adjustStock(
                $recipeItem->ingredient,
                InventoryMovementType::Consumo,
                -$required,
                $userId,
                reason: "Venta {$item->order->folio}",
                reference: $item,
            );
        }
    }

    /**
     * Revierte el consumo de insumos de una orden cancelada.
     */
    public function restockForOrder(Order $order, int $userId): void
    {
        $items = $order->items()->with('product.recipeItems.ingredient')->get();

        foreach ($items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_inventoried) {
                continue;
            }

            foreach ($product->recipeItems as $recipeItem) {
                $amount = (float) $recipeItem->quantity * (float) $item->quantity;

                $this->adjustStock(
                    $recipeItem->ingredient,
                    InventoryMovementType::Devolucion,
                    $amount,
                    $userId,
                    reason: "Cancelación venta {$order->folio}",
                    reference: $item,
                );
            }
        }
    }
}
