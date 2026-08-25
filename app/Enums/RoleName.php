<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Administrador = 'administrador';
    case Encargado = 'encargado';
    case Cajero = 'cajero';
    case Mesero = 'mesero';
    case Cocina = 'cocina';
    case Barra = 'barra';
    case Inventarios = 'inventarios';
    case Reportes = 'reportes';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Administrador => 'Administrador',
            self::Encargado => 'Encargado',
            self::Cajero => 'Cajero',
            self::Mesero => 'Mesero',
            self::Cocina => 'Cocina',
            self::Barra => 'Barra',
            self::Inventarios => 'Inventarios',
            self::Reportes => 'Reportes',
        };
    }
}
