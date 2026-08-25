<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCancellation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Crea y cierra una venta en una sola transacción: líneas, modificadores y pagos.
     *
     * @param  array{
     *     business_id: int, branch_id: int, terminal_id: ?int, user_id: int, customer_id: ?int,
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
            $subtotal = 0.0;
            $taxAmount = 0.0;

            $lines = [];

            foreach ($data['items'] as $item) {
                $modifiersTotal = array_sum(array_column($item['modifiers'], 'price_delta'));
                $lineUnitPrice = $item['unit_price'] + $modifiersTotal;
                $lineSubtotal = round($lineUnitPrice * $item['quantity'], 2);
                $lineTax = round($lineSubtotal * ($item['tax_rate'] / 100), 2);

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;

                $lines[] = $item + ['subtotal' => $lineSubtotal];
            }

            $subtotal = round($subtotal, 2);
            $taxAmount = round($taxAmount, 2);

            $discountAmount = $this->calculateDiscount($subtotal, $data['discount_type'] ?? null, $data['discount_value'] ?? null);

            $tipAmount = round($data['tip_amount'] ?? 0, 2);

            $total = round($subtotal - $discountAmount + $taxAmount + $tipAmount, 2);

            $paymentsTotal = round(array_sum(array_column($data['payments'], 'amount')), 2);

            if ($paymentsTotal + 0.005 < $total) {
                throw new InvalidArgumentException('El monto pagado es insuficiente para cubrir el total.');
            }

            $order = Order::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'terminal_id' => $data['terminal_id'],
                'user_id' => $data['user_id'],
                'customer_id' => $data['customer_id'],
                'folio' => $this->folios->next($data['business_id'], 'venta'),
                'order_type' => $data['order_type'],
                'status' => OrderStatus::Completed,
                'subtotal' => $subtotal,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'tip_amount' => $tipAmount,
                'total' => $total,
                'notes' => $data['notes'] ?? null,
                'completed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $orderItem = $order->items()->create([
                    'product_id' => $line['product_id'],
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'notes' => $line['notes'] ?? null,
                    'subtotal' => $line['subtotal'],
                ]);

                foreach ($line['modifiers'] as $modifier) {
                    $orderItem->modifiers()->create($modifier);
                }
            }

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
            }

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

            $this->auditLogger->log('venta.anular', $order, $before, ['status' => OrderStatus::Cancelled->value, 'reason' => $reason]);

            return $order->fresh();
        });
    }

    private function calculateDiscount(float $subtotal, ?string $type, ?float $value): float
    {
        if (! $type || ! $value) {
            return 0.0;
        }

        return round($type === 'percentage' ? $subtotal * ($value / 100) : min($value, $subtotal), 2);
    }
}
