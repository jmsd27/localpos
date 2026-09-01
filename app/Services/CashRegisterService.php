<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CashRegisterSessionStatus;
use App\Enums\PaymentMethod;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashRegisterService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function open(int $cashRegisterId, ?int $terminalId, int $userId, float $openingAmount): CashRegisterSession
    {
        $existing = CashRegisterSession::query()
            ->where('cash_register_id', $cashRegisterId)
            ->where('status', CashRegisterSessionStatus::Open)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Esta caja ya tiene una sesión abierta.');
        }

        $session = CashRegisterSession::create([
            'cash_register_id' => $cashRegisterId,
            'terminal_id' => $terminalId,
            'opened_by_user_id' => $userId,
            'opening_amount' => $openingAmount,
            'opened_at' => now(),
            'status' => CashRegisterSessionStatus::Open,
        ]);

        $this->auditLogger->log('caja.abrir', $session, null, $session->only(['opening_amount']));

        return $session;
    }

    public function addMovement(CashRegisterSession $session, CashMovementType $type, float $amount, int $userId, ?string $reason = null, ?int $orderId = null, PaymentMethod $paymentMethod = PaymentMethod::Efectivo): CashMovement
    {
        $movement = $session->movements()->create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'type' => $type,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $this->auditLogger->log('caja.'.$type->value, $movement, null, $movement->only(['amount', 'reason']));

        return $movement;
    }

    public function expectedCash(CashRegisterSession $session): float
    {
        $movementsCash = $session->movements()
            ->where('payment_method', PaymentMethod::Efectivo)
            ->sum('amount');

        return round((float) $session->opening_amount + (float) $movementsCash, 2);
    }

    /**
     * @param  list<array{value: float, label: string, quantity: int, subtotal: float}>|null  $denominations
     */
    public function close(CashRegisterSession $session, float $countedCash, int $userId, ?string $notes = null, ?array $denominations = null): CashRegisterSession
    {
        if ($session->status === CashRegisterSessionStatus::Closed) {
            throw new InvalidArgumentException('Esta sesión de caja ya está cerrada.');
        }

        return DB::transaction(function () use ($session, $countedCash, $userId, $notes, $denominations) {
            $expected = $this->expectedCash($session);
            $difference = round($countedCash - $expected, 2);

            $session->update([
                'status' => CashRegisterSessionStatus::Closed,
                'closed_by_user_id' => $userId,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'denominations' => $denominations,
                'difference' => $difference,
                'notes' => $notes,
            ]);

            $this->auditLogger->log('caja.cerrar', $session, null, $session->only([
                'expected_cash', 'counted_cash', 'difference',
            ]));

            return $session->fresh();
        });
    }

    /**
     * Reporte de cierre: ventas por método de pago, ingresos, retiros, ajustes,
     * cancelaciones, descuentos y propinas totales de la sesión.
     */
    public function closingReport(CashRegisterSession $session): array
    {
        $salesByMethod = $session->movements()
            ->where('type', CashMovementType::Venta)
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $cancellations = (float) $session->movements()->where('type', CashMovementType::Cancelacion)->sum('amount');
        $incomes = (float) $session->movements()->where('type', CashMovementType::Ingreso)->sum('amount');
        $withdrawals = (float) $session->movements()->where('type', CashMovementType::Retiro)->sum('amount');
        $adjustments = (float) $session->movements()->where('type', CashMovementType::Ajuste)->sum('amount');

        $orders = $session->orders()->get(['discount_amount', 'tip_amount']);

        return [
            'opening_amount' => (float) $session->opening_amount,
            'sales_by_method' => collect(PaymentMethod::cases())->mapWithKeys(fn ($method) => [
                $method->value => (float) ($salesByMethod[$method->value] ?? 0),
            ]),
            'cancellations' => $cancellations,
            'incomes' => $incomes,
            'withdrawals' => $withdrawals,
            'adjustments' => $adjustments,
            'discounts_total' => (float) $orders->sum('discount_amount'),
            'tips_total' => (float) $orders->sum('tip_amount'),
            'expected_cash' => $this->expectedCash($session),
        ];
    }
}
