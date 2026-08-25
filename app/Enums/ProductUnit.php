<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Pieza = 'pieza';
    case Kg = 'kg';
    case Gramo = 'gramo';
    case Litro = 'litro';
    case Mililitro = 'mililitro';
    case Caja = 'caja';
    case Botella = 'botella';

    public function label(): string
    {
        return match ($this) {
            self::Pieza => 'Pieza',
            self::Kg => 'Kilogramo',
            self::Gramo => 'Gramo',
            self::Litro => 'Litro',
            self::Mililitro => 'Mililitro',
            self::Caja => 'Caja',
            self::Botella => 'Botella',
        };
    }
}
