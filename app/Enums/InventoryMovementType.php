<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
    case Merma = 'merma';
    case Consumo = 'consumo';
    case Compra = 'compra';
    case Devolucion = 'devolucion';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::Ajuste => 'Ajuste',
            self::Merma => 'Merma',
            self::Consumo => 'Consumo',
            self::Compra => 'Compra',
            self::Devolucion => 'Devolución',
            self::Transferencia => 'Transferencia',
        };
    }
}
