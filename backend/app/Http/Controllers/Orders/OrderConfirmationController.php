<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\ConfirmOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderConfirmationController extends Controller
{
    /**
     * Confirms a Pending Order and reserves its stock. Authorized by the route
     * (`can:confirm,order`); safe to retry with an `Idempotency-Key` header.
     */
    public function store(Order $order, ConfirmOrder $confirmOrder): OrderResource
    {
        return new OrderResource(OrderController::loadForDisplay($confirmOrder->handle($order)));
    }
}
