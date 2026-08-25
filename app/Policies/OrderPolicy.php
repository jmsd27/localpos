<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->businessId() === $order->business_id && $user->can('ventas.ver');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->businessId() === $order->business_id && $user->can('ventas.anular');
    }
}
