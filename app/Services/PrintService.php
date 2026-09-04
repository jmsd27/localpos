<?php

namespace App\Services;

use App\Enums\PrintJobStatus;
use App\Enums\PrintJobType;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrintJob;
use App\Models\Terminal;
use Illuminate\Support\Collection;

class PrintService
{
    /**
     * Ancho de impresión (en caracteres) usado por el trabajo que se está
     * renderizando en este momento. Se recalcula al inicio de cada
     * `render*()` según el terminal de destino, con 48 como respaldo cuando
     * el terminal no tiene `paper_width_chars` configurado (p. ej. no
     * existe o el campo es null).
     */
    private const DEFAULT_WIDTH = 48;

    private int $width = self::DEFAULT_WIDTH;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Encola el ticket de venta en la cola de impresión del terminal donde se
     * cobró. Si algún pago fue en efectivo, marca la apertura del cajón.
     */
    public function enqueueSaleTicket(Order $order): PrintJob
    {
        $order->loadMissing(['items.modifiers', 'payments', 'business', 'user', 'customer', 'terminal']);

        $hasCash = $order->payments->contains(fn ($payment) => $payment->method->value === 'efectivo');

        return PrintJob::create([
            'business_id' => $order->business_id,
            'branch_id' => $order->branch_id,
            'terminal_id' => $order->terminal_id,
            'type' => PrintJobType::TicketVenta,
            'status' => PrintJobStatus::Pendiente,
            'content' => $this->renderSaleTicket($order),
            'open_drawer' => $hasCash,
            'reference_type' => $order->getMorphClass(),
            'reference_id' => $order->id,
        ]);
    }

    /**
     * Agrupa las líneas recién enviadas por estación de cocina/barra y encola
     * una comanda impresa por cada estación (solo para productos con estación
     * asignada; el resto ya se ve en el KDS).
     *
     * @param  Collection<int, OrderItem>  $items
     * @return list<PrintJob>
     */
    public function enqueueKitchenComandas(Order $order, Collection $items): array
    {
        $order->loadMissing('table');

        $grouped = $items->filter(fn (OrderItem $item) => $item->kitchen_station_id !== null)
            ->groupBy('kitchen_station_id');

        $jobs = [];

        foreach ($grouped as $stationId => $stationItems) {
            $station = KitchenStation::find($stationId);

            $jobs[] = PrintJob::create([
                'business_id' => $order->business_id,
                'branch_id' => $order->branch_id,
                'terminal_id' => $station?->printer_terminal_id,
                'type' => PrintJobType::ComandaCocina,
                'status' => PrintJobStatus::Pendiente,
                'content' => $this->renderKitchenComanda($order, $stationItems, $station),
                'reference_type' => $order->getMorphClass(),
                'reference_id' => $order->id,
            ]);
        }

        return $jobs;
    }

    public function openCashDrawer(int $terminalId, int $userId): PrintJob
    {
        $terminal = Terminal::findOrFail($terminalId);

        $job = PrintJob::create([
            'business_id' => $terminal->business_id,
            'branch_id' => $terminal->branch_id,
            'terminal_id' => $terminal->id,
            'type' => PrintJobType::AperturaCajon,
            'status' => PrintJobStatus::Pendiente,
            'open_drawer' => true,
        ]);

        $this->auditLogger->log('impresion.abrir_cajon', $job, null, ['terminal_id' => $terminalId, 'user_id' => $userId]);

        return $job;
    }

    public function markPrinted(PrintJob $job): PrintJob
    {
        $job->update(['status' => PrintJobStatus::Impreso, 'printed_at' => now()]);

        return $job->fresh();
    }

    public function markFailed(PrintJob $job, string $message): PrintJob
    {
        $job->update([
            'status' => PrintJobStatus::Error,
            'error_message' => $message,
            'attempts' => $job->attempts + 1,
        ]);

        return $job->fresh();
    }

    public function retry(PrintJob $job): PrintJob
    {
        $job->update(['status' => PrintJobStatus::Pendiente, 'error_message' => null]);

        return $job->fresh();
    }

    private function renderSaleTicket(Order $order): string
    {
        $this->width = $order->terminal?->paper_width_chars ?? self::DEFAULT_WIDTH;

        $lines = [];
        $lines[] = $this->center($order->business->name);

        if ($order->business->address) {
            $lines[] = $this->center($order->business->address);
        }

        if ($order->business->phone) {
            $lines[] = $this->center('Tel: '.$order->business->phone);
        }

        $lines[] = $this->rule();
        $lines[] = 'Folio: '.$order->folio;
        $lines[] = 'Fecha: '.$order->completed_at?->format('d/m/Y H:i');
        $lines[] = 'Cajero: '.$order->user->name;

        if ($order->customer) {
            $lines[] = 'Cliente: '.$order->customer->name;
        }

        $lines[] = $this->rule();

        foreach ($order->items as $item) {
            $lines[] = $this->row("{$item->quantity} x {$item->name}", '$'.number_format((float) $item->subtotal, 2));

            foreach ($item->modifiers as $modifier) {
                $lines[] = '  + '.$modifier->name;
            }

            if ($item->notes) {
                $lines[] = '  '.$item->notes;
            }
        }

        $lines[] = $this->rule();
        $lines[] = $this->row('Subtotal', '$'.number_format((float) $order->subtotal, 2));

        if ((float) $order->discount_amount > 0) {
            $lines[] = $this->row('Descuento', '-$'.number_format((float) $order->discount_amount, 2));
        }

        $lines[] = $this->row('IVA', '$'.number_format((float) $order->tax_amount, 2));

        if ((float) $order->tip_amount > 0) {
            $lines[] = $this->row('Propina', '$'.number_format((float) $order->tip_amount, 2));
        }

        $lines[] = $this->row('TOTAL', '$'.number_format((float) $order->total, 2));
        $lines[] = $this->rule();

        foreach ($order->payments as $payment) {
            $lines[] = $this->row($payment->method->label(), '$'.number_format((float) $payment->amount, 2));

            if ($payment->change_amount && (float) $payment->change_amount > 0) {
                $lines[] = $this->row('Cambio', '$'.number_format((float) $payment->change_amount, 2));
            }
        }

        $lines[] = $this->rule();
        $lines[] = $this->center('¡Gracias por su compra!');

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function renderKitchenComanda(Order $order, Collection $items, ?KitchenStation $station): string
    {
        $this->width = $station?->printerTerminal?->paper_width_chars ?? self::DEFAULT_WIDTH;

        $lines = [];
        $lines[] = $this->center($station?->name ?? 'Cocina');
        $lines[] = $this->rule();
        $lines[] = $order->table ? 'Mesa: '.$order->table->name : 'Mostrador';
        $lines[] = 'Folio: '.($order->comanda_folio ?? $order->folio ?? '—');
        $lines[] = 'Hora: '.now()->format('d/m/Y H:i');
        $lines[] = $this->rule();

        foreach ($items as $item) {
            $lines[] = "{$item->quantity} x {$item->name}";

            foreach ($item->modifiers as $modifier) {
                $lines[] = '  + '.$modifier->name;
            }

            if ($item->notes) {
                $lines[] = '  NOTA: '.$item->notes;
            }
        }

        $lines[] = $this->rule();

        return implode("\n", $lines);
    }

    private function center(string $text): string
    {
        $padding = max(0, intdiv($this->width - strlen($text), 2));

        return str_repeat(' ', $padding).$text;
    }

    private function rule(): string
    {
        return str_repeat('-', $this->width);
    }

    private function row(string $label, string $value): string
    {
        $space = max(1, $this->width - strlen($label) - strlen($value));

        return $label.str_repeat(' ', $space).$value;
    }
}
