<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo = 'efectivo';
    case Tarjeta = 'tarjeta';
    case Transferencia = 'transferencia';
    case Credito = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Tarjeta => 'Tarjeta',
            self::Transferencia => 'Transferencia',
            self::Credito => 'Crédito',
        };
    }
}
