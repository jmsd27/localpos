<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CashRegisterSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableStatus;
use App\Models\CashRegisterSession;
use App\Models\Order;
use App\Models\OrderCancellation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly AuditLogger $auditLogger,
        private readonly CashRegisterService $cashRegisters,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Crea una venta directa (mostrador/para llevar) y la cobra en un solo paso.
     *
     * @param  array{
     *     business_id: int, branch_id: int, terminal_id: ?int, cash_register_session_id: ?int, user_id: int, customer_id: ?int,
     *     order_type: string,
     *     items: list<array{product_id: int, name: string, quantity: float, unit_price: float, tax_rate: float, notes: ?string, modifiers: list<array{modifier_option_id: ?int, name: string, price_delta: float}>}>,
     *     discount_type: ?string, discount_value: ?float,
     *     tip_amount: float,
     *     payments: list<array{method: string, amount: float, received_amount: ?float}>,
     * }  $data
     */
    public function complete(array $data): Order
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('La venta no tiene productos.');
        }

        return DB::transaction(function () use ($data) {
            $order = $this->createDraftOrder($data);
            $this->addItemsToOrder($order, $data['items']);

            return $this->payOrder($order->fresh(), [
                'payments' => $data['payments'],
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'tip_amount' => $data['tip_amount'] ?? 0,
                'user_id' => $data['user_id'],
            ]);
        });
    }

    /**
     * Abre una comanda (orden pendiente, sin cobrar todavía). Usado por mesas y,
     * internamente, por complete() para ventas directas.
     */
    public function createDraftOrder(array $data): Order
    {
        $order = Order::create([
            'business_id' => $data['business_id'],
            'branch_id' => $data['branch_id'],
            'terminal_id' => $data['terminal_id'] ?? null,
            'cash_register_session_id' => $data['cash_register_session_id'] ?? null,
            'user_id' => $data['user_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'table_id' => $data['table_id'] ?? null,
            'people_count' => $data['people_count'] ?? null,
            'comanda_folio' => ($data['table_id'] ?? null) ? $this->folios->next($data['business_id'], 'comanda') : null,
            'order_type' => $data['order_type'],
            'status' => OrderStatus::Pending,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'tip_amount' => 0,
            'total' => 0,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($order->table_id) {
            $order->table->update(['status' => TableStatus::Occupied]);
        }

        return $order;
    }

    /**
     * Agrega productos (y sus modificadores) a una orden pendiente y recalcula totales.
     */
    public function addItemsToOrder(Order $order, array $items): Order
    {
        return DB::transaction(function () use ($order, $items) {
            foreach ($items as $item) {
                $modifiersTotal = array_sum(array_column($item['modifiers'], 'price_delta'));
                $lineSubtotal = round(($item['unit_price'] + $modifiersTotal) * $item['quantity'], 2);

                $orderItem = $order->items()->create([
                    'product_id' => $item['product_id'],
                    'kitchen_station_id' => $item['kitchen_station_id'] ?? null,
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'notes' => $item['notes'] ?? null,
                    'subtotal' => $lineSubtotal,
                ]);

                foreach ($item['modifiers'] as $modifier) {
                    $orderItem->modifiers()->create($modifier);
                }
            }

            $this->recalculateTotals($order);

            return $order->fresh(['items.modifiers']);
        });
    }

    public function removeOrderItem(Order $order, int $orderItemId): Order
    {
        $order->items()->where('id', $orderItemId)->delete();

        $this->recalculateTotals($order);

        return $order->fresh(['items.modifiers']);
    }

    public function applyDiscount(Order $order, ?string $type, ?float $value): Order
    {
        $order->update(['discount_type' => $type, 'discount_value' => $value]);

        $this->recalculateTotals($order);

        return $order->fresh();
    }

    public function applyTip(Order $order, float $tipAmount): Order
    {
        $order->update(['tip_amount' => round($tipAmount, 2)]);

        $this->recalculateTotals($order);

        return $order->fresh();
    }

    /**
     * Marca la mesa de la orden como "por cobrar" (el mesero pidió la cuenta).
     */
    public function requestBill(Order $order): Order
    {
        if ($order->table_id) {
            $order->table->update(['status' => TableStatus::ToPay]);
        }

        return $order->fresh();
    }

    /**
     * Cancela una comanda pendiente sin cobro (mesa vacía, pedido equivocado, etc.).
     */
    public function voidDraftOrder(Order $order, int $userId, string $reason): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('Solo se pueden vaciar comandas pendientes.');
        }

        return DB::transaction(function () use ($order, $userId, $reason) {
            $order->update(['status' => OrderStatus::Cancelled]);

            if ($order->table_id) {
                $order->table->update(['status' => TableStatus::Available]);
            }

            $this->auditLogger->log('comanda.vaciar', $order, null, ['reason' => $reason, 'user_id' => $userId]);

            return $order->fresh();
        });
    }

    /**
     * Finaliza el cobro de una orden pendiente (directa o de mesa): aplica
     * descuento/propina finales, valida los pagos, genera folio y libera la mesa.
     *
     * @param  array{payments: list<array{method: string, amount: float, received_amount: ?float}>, discount_type?: ?string, discount_value?: ?float, tip_amount?: float, user_id: int, terminal_id?: ?int, cash_register_session_id?: ?int, customer_id?: ?int}  $data
     */
    public function payOrder(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            if ($order->status !== OrderStatus::Pending) {
                throw new InvalidArgumentException('Esta orden ya fue procesada.');
            }

            if ($order->items()->doesntExist()) {
                throw new InvalidArgumentException('La venta no tiene productos.');
            }

            $order->update([
                'discount_type' => array_key_exists('discount_type', $data) ? $data['discount_type'] : $order->discount_type,
                'discount_value' => array_key_exists('discount_value', $data) ? $data['discount_value'] : $order->discount_value,
                'tip_amount' => round($data['tip_amount'] ?? (float) $order->tip_amount, 2),
                'terminal_id' => $data['terminal_id'] ?? $order->terminal_id,
                'cash_register_session_id' => $data['cash_register_session_id'] ?? $order->cash_register_session_id,
                'customer_id' => $data['customer_id'] ?? $order->customer_id,
            ]);

            $this->recalculateTotals($order);
            $order->refresh();

            $paymentsTotal = round(array_sum(array_column($data['payments'], 'amount')), 2);

            if ($paymentsTotal + 0.005 < (float) $order->total) {
                throw new InvalidArgumentException('El monto pagado es insuficiente para cubrir el total.');
            }

            $order->update([
                'folio' => $order->folio ?? $this->folios->next($order->business_id, 'venta'),
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);

            $session = $order->cash_register_session_id ? CashRegisterSession::find($order->cash_register_session_id) : null;
            $movementUserId = $data['user_id'];

            foreach ($data['payments'] as $payment) {
                $isCash = $payment['method'] === 'efectivo';
                $received = $payment['received_amount'] ?? null;

                $order->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'received_amount' => $received,
                    'change_amount' => $isCash && $received !== null
                        ? max(0, round($received - $payment['amount'], 2))
                        : null,
                ]);

                if ($session && $session->status === CashRegisterSessionStatus::Open) {
                    $this->cashRegisters->addMovement(
                        $session,
                        CashMovementType::Venta,
                        $payment['amount'],
                        $movementUserId,
                        orderId: $order->id,
                        paymentMethod: PaymentMethod::from($payment['method']),
                    );
                }
            }

            if ($order->table_id) {
                $order->table->update(['status' => TableStatus::Available]);
            }

            $this->inventory->consumeForOrder($order, $movementUserId);

            $this->auditLogger->log('venta.crear', $order, null, $order->only([
                'folio', 'subtotal', 'discount_amount', 'tax_amount', 'tip_amount', 'total',
            ]));

            return $order->fresh(['items.modifiers', 'payments']);
        });
    }

    public function cancel(Order $order, int $userId, string $reason): Order
    {
        return DB::transaction(function () use ($order, $userId, $reason) {
            $before = $order->only(['status']);

            $order->update(['status' => OrderStatus::Cancelled]);

            OrderCancellation::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'reason' => $reason,
                'amount' => $order->total,
                'created_at' => now(),
            ]);

            $this->inventory->restockForOrder($order, $userId);

            $session = $order->cash_register_session_id ? CashRegisterSession::find($order->cash_register_session_id) : null;

            if ($session && $session->status === CashRegisterSessionStatus::Open) {
                foreach ($order->payments as $payment) {
                    $this->cashRegisters->addMovement(
                        $session,
                        CashMovementType::Cancelacion,
                        -$payment->amount,
                        $userId,
                        reason: $reason,
                        orderId: $order->id,
                        paymentMethod: $payment->method,
                    );
                }
            }

            $this->auditLogger->log('venta.anular', $order, $before, ['status' => OrderStatus::Cancelled->value, 'reason' => $reason]);

            return $order->fresh();
        });
    }

    private function recalculateTotals(Order $order): void
    {
        $items = $order->items()->get();

        $subtotal = round((float) $items->sum('subtotal'), 2);
        $taxAmount = round((float) $items->sum(fn ($item) => (float) $item->subtotal * ((float) $item->tax_rate / 100)), 2);

        $discountAmount = $this->calculateDiscount(
            $subtotal,
            $order->discount_type?->value,
            $order->discount_value !== null ? (float) $order->discount_value : null,
        );

        $tipAmount = round((float) $order->tip_amount, 2);

        $total = round($subtotal - $discountAmount + $taxAmount + $tipAmount, 2);

        $order->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    private function calculateDiscount(float $subtotal, ?string $type, ?float $value): float
    {
        if (! $type || ! $value) {
            return 0.0;
        }

        return round($type === 'percentage' ? $subtotal * ($value / 100) : min($value, $subtotal), 2);
    }
}
