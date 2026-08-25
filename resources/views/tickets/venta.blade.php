<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $order->folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 8px;
            color: #000;
        }
        h1 { font-size: 14px; text-align: center; margin: 0 0 4px; }
        .center { text-align: center; }
        .muted { color: #444; font-size: 11px; }
        hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; }
        .totals td { padding: 1px 0; }
        .grand { font-size: 13px; font-weight: bold; }
        .modifier, .notes { font-size: 10px; color: #333; padding-left: 8px; }
        @media print {
            body { width: 100%; }
        }
    </style>
</head>
<body>
    <h1>{{ $order->business->name }}</h1>
    @if ($order->business->address)
        <p class="center muted">{{ $order->business->address }}</p>
    @endif
    @if ($order->business->phone)
        <p class="center muted">Tel: {{ $order->business->phone }}</p>
    @endif

    <hr>

    <p class="muted">
        Folio: <strong>{{ $order->folio }}</strong><br>
        Fecha: {{ $order->completed_at?->format('d/m/Y H:i') }}<br>
        Cajero: {{ $order->user->name }}<br>
        Tipo: {{ $order->order_type->label() }}
        @if ($order->customer) <br>Cliente: {{ $order->customer->name }} @endif
    </p>

    <hr>

    <table>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->quantity }} x {{ $item->name }}</td>
                <td class="right">${{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
            @foreach ($item->modifiers as $modifier)
                <tr>
                    <td class="modifier" colspan="2">+ {{ $modifier->name }}</td>
                </tr>
            @endforeach
            @if ($item->notes)
                <tr>
                    <td class="notes" colspan="2">{{ $item->notes }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <hr>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">${{ number_format((float) $order->subtotal, 2) }}</td></tr>
        @if ((float) $order->discount_amount > 0)
            <tr><td>Descuento</td><td class="right">-${{ number_format((float) $order->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td>IVA</td><td class="right">${{ number_format((float) $order->tax_amount, 2) }}</td></tr>
        @if ((float) $order->tip_amount > 0)
            <tr><td>Propina</td><td class="right">${{ number_format((float) $order->tip_amount, 2) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="right">${{ number_format((float) $order->total, 2) }}</td></tr>
    </table>

    <hr>

    <table>
        @foreach ($order->payments as $payment)
            <tr>
                <td>{{ $payment->method->label() }}</td>
                <td class="right">${{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
            @if ($payment->change_amount && (float) $payment->change_amount > 0)
                <tr>
                    <td class="muted">Cambio</td>
                    <td class="right muted">${{ number_format((float) $payment->change_amount, 2) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <hr>

    <p class="center muted">¡Gracias por su compra!</p>

    <button onclick="window.print()" class="no-print" style="display: block; width: 100%; margin-top: 12px; padding: 8px; font-family: inherit; font-size: 12px;">
        Imprimir
    </button>

    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</body>
</html>
