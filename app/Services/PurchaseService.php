<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseStatus;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly InventoryService $inventory,
        private readonly FolioGenerator $folios,
    ) {}

    /**
     * Crea una compra en borrador con sus líneas. No afecta el inventario
     * todavía — el stock solo se mueve al recibirla.
     */
    public function create(array $data): Purchase
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('La compra debe tener al menos un insumo.');
        }

        return DB::transaction(function () use ($data) {
            $total = 0;

            foreach ($data['items'] as $item) {
                $total += round((float) $item['quantity'] * (float) $item['unit_cost'], 2);
            }

            $purchase = Purchase::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'user_id' => $data['user_id'],
                'folio' => $this->folios->next($data['business_id'], 'compra'),
                'status' => PurchaseStatus::Borrador,
                'total' => $total,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $subtotal = round((float) $item['quantity'] * (float) $item['unit_cost'], 2);

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $subtotal,
                ]);
            }

            $this->auditLogger->log('compras.crear', $purchase, null, $purchase->only(['supplier_id', 'total']));

            return $purchase->load('items.ingredient', 'supplier');
        });
    }

    /**
     * Marca la compra como recibida: entra el stock de cada insumo y
     * actualiza su costo por unidad al último costo de compra.
     */
    public function receive(Purchase $purchase, int $userId): Purchase
    {
        if ($purchase->status !== PurchaseStatus::Borrador) {
            throw new InvalidArgumentException('Solo se puede recibir una compra en borrador.');
        }

        return DB::transaction(function () use ($purchase, $userId) {
            foreach ($purchase->items()->with('ingredient')->get() as $item) {
                $this->inventory->adjustStock(
                    $item->ingredient,
                    InventoryMovementType::Compra,
                    (float) $item->quantity,
                    $userId,
                    reason: "Compra {$purchase->folio}",
                    reference: $purchase,
                );

                Ingredient::query()->where('id', $item->ingredient_id)->update(['cost_per_unit' => $item->unit_cost]);
            }

            $purchase->update([
                'status' => PurchaseStatus::Recibida,
                'received_at' => now(),
            ]);

            $this->auditLogger->log('compras.recibir', $purchase, null, ['status' => $purchase->status->value]);

            return $purchase->fresh(['items.ingredient', 'supplier']);
        });
    }

    /**
     * Cancela una compra. Si ya había sido recibida, revierte el stock
     * que había ingresado.
     */
    public function cancel(Purchase $purchase, int $userId, string $reason): Purchase
    {
        if ($purchase->status === PurchaseStatus::Cancelada) {
            throw new InvalidArgumentException('Esta compra ya está cancelada.');
        }

        return DB::transaction(function () use ($purchase, $userId, $reason) {
            if ($purchase->status === PurchaseStatus::Recibida) {
                foreach ($purchase->items()->with('ingredient')->get() as $item) {
                    $this->inventory->adjustStock(
                        $item->ingredient,
                        InventoryMovementType::Ajuste,
                        -(float) $item->quantity,
                        $userId,
                        reason: "Cancelación compra {$purchase->folio}",
                        reference: $purchase,
                    );
                }
            }

            $purchase->update([
                'status' => PurchaseStatus::Cancelada,
                'cancel_reason' => $reason,
            ]);

            $this->auditLogger->log('compras.cancelar', $purchase, null, ['cancel_reason' => $reason]);

            return $purchase->fresh(['items.ingredient', 'supplier']);
        });
    }
}
