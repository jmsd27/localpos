<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Pendiente = 'pendiente';
    case Impreso = 'impreso';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Impreso => 'Impreso',
            self::Error => 'Error',
        };
    }
}
