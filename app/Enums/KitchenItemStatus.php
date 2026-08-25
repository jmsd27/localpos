<?php

namespace App\Enums;

enum KitchenItemStatus: string
{
    case Nuevo = 'nuevo';
    case Preparando = 'preparando';
    case Listo = 'listo';
    case Entregado = 'entregado';

    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Preparando => 'En preparación',
            self::Listo => 'Listo',
            self::Entregado => 'Entregado',
        };
    }
}
