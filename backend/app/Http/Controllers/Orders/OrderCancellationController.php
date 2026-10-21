<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderCancellationController extends Controller
{
    /**
     * Cancels the whole Order and releases any Reserved stock. Safe to retry with an
     * `Idempotency-Key` header.
     */
    public function store(CancelOrderRequest $request, Order $order, CancelOrder $cancelOrder): OrderResource
    {
        return new OrderResource($cancelOrder->handle($order, $request->note())->loadForDisplay());
    }
}
