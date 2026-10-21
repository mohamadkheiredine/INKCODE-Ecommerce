<?php

namespace App\Http\Controllers\Returns;

use App\Actions\Returns\RefundReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\RefundReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\OrderReturn;

/**
 * Received → Completed with its Refund (refunds.issue). Refused with 409
 * `invalid_return_transition`, `restock_decisions_pending`, `shipping_already_refunded`
 * or `refund_exceeds_cap`.
 */
class ReturnRefundController extends Controller
{
    public function store(RefundReturnRequest $request, OrderReturn $orderReturn, RefundReturn $refundReturn): ReturnResource
    {
        return new ReturnResource(
            $refundReturn->handle($orderReturn, $request->amount(), $request->refundShipping(), $request->idempotencyKey())->loadForDisplay(),
        );
    }
}
