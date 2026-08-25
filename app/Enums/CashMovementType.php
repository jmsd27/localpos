<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Venta = 'venta';
    case Ingreso = 'ingreso';
    case Retiro = 'retiro';
    case Devolucion = 'devolucion';
    case Cancelacion = 'cancelacion';
    case Ajuste = 'ajuste';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Ingreso => 'Ingreso',
            self::Retiro => 'Retiro',
            self::Devolucion => 'Devolución',
            self::Cancelacion => 'Cancelación',
            self::Ajuste => 'Ajuste',
        };
    }
}
