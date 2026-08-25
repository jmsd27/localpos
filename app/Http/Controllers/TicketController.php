<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __invoke(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['items.modifiers', 'payments', 'business', 'branch', 'user', 'customer']);

        return view('tickets.venta', ['order' => $order]);
    }
}
