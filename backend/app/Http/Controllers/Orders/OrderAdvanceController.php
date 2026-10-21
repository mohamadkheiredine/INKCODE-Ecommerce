<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\AdvanceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AdvanceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderAdvanceController extends Controller
{
    /**
     * Moves the Order one step forward (Preparing, Ready for shipping). A skipped or
     * backward move is refused with 409 `invalid_transition` and `details.allowed`. Safe
     * to retry with an `Idempotency-Key` header.
     */
    public function store(AdvanceOrderRequest $request, Order $order, AdvanceOrder $advanceOrder): OrderResource
    {
        return new OrderResource($advanceOrder->handle($order, $request->status())->loadForDisplay());
    }
}
