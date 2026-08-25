<?php

namespace App\Enums;

enum PrintJobType: string
{
    case TicketVenta = 'ticket_venta';
    case ComandaCocina = 'comanda_cocina';
    case AperturaCajon = 'apertura_cajon';

    public function label(): string
    {
        return match ($this) {
            self::TicketVenta => 'Ticket de venta',
            self::ComandaCocina => 'Comanda de cocina',
            self::AperturaCajon => 'Apertura de cajón',
        };
    }
}
