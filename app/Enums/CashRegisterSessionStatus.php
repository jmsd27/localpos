<?php

namespace App\Enums;

enum CashRegisterSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
