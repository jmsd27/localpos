<?php

namespace App\Services;

use App\Enums\KitchenItemStatus;
use App\Models\OrderItem;

class KitchenService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function advance(OrderItem $item, KitchenItemStatus $to): OrderItem
    {
        $before = ['kitchen_status' => $item->kitchen_status->value];

        $timestamps = match ($to) {
            KitchenItemStatus::Preparando => ['started_at' => now()],
            KitchenItemStatus::Listo => ['ready_at' => now()],
            KitchenItemStatus::Entregado => ['delivered_at' => now()],
            KitchenItemStatus::Nuevo => [],
        };

        $item->update(['kitchen_status' => $to, ...$timestamps]);

        $this->auditLogger->log('comanda.avanzar_estado', $item, $before, ['kitchen_status' => $to->value]);

        return $item->fresh();
    }
}
