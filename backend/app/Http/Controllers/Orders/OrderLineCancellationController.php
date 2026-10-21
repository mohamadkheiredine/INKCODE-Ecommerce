<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CancelOrderLines;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderLinesRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;

class OrderLineCancellationController extends Controller
{
    /**
     * Partial cancellation: cancels some units of some lines before the Order ships,
     * releasing their stock and re-pricing the Order. Safe to retry with an
     * `Idempotency-Key` header.
     */
    public function store(CancelOrderLinesRequest $request, Order $order, CancelOrderLines $cancelOrderLines): OrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new OrderResource($cancelOrderLines->handle($order, $request->cancellations(), $user, $request->note())->loadForDisplay());
    }
}
