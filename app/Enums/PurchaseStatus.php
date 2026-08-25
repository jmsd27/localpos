<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Borrador = 'borrador';
    case Recibida = 'recibida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Recibida => 'Recibida',
            self::Cancelada => 'Cancelada',
        };
    }
}
