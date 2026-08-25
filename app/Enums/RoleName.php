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
}
