<?php

namespace App\Enums;

enum OrderType: string
{
    case Mostrador = 'mostrador';
    case ParaLlevar = 'para_llevar';
    case Mesa = 'mesa';

    public function label(): string
    {
        return match ($this) {
            self::Mostrador => 'Mostrador',
            self::ParaLlevar => 'Para llevar',
            self::Mesa => 'Mesa',
        };
    }
}
