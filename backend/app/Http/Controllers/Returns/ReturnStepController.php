<?php

namespace App\Http\Controllers\Returns;

use App\Actions\Returns\DecideRestock;
use App\Actions\Returns\MoveReturn;
use App\Actions\Returns\RefundReturn;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\DecideReturnRequest;
use App\Http\Requests\Returns\RefundReturnRequest;
use App\Http\Requests\Returns\RestockReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\OrderReturn;

/**
 * Moves a Return through its steps; each answers with the Return. An invalid step is
 * refused with 409 `invalid_return_transition`.
 */
class ReturnStepController extends Controller
{
    /** Requested → Approved (returns.approve). */
    public function approve(DecideReturnRequest $request, OrderReturn $orderReturn, MoveReturn $moveReturn): ReturnResource
    {
        return new ReturnResource(ReturnController::loadForDisplay($moveReturn->handle($orderReturn, ReturnStatus::Approved, $request->note())));
    }

    /** Requested → Rejected (returns.approve); its units may be requested again. */
    public function reject(DecideReturnRequest $request, OrderReturn $orderReturn, MoveReturn $moveReturn): ReturnResource
    {
        return new ReturnResource(ReturnController::loadForDisplay($moveReturn->handle($orderReturn, ReturnStatus::Rejected, $request->note())));
    }

    /** Approved → Received, when the items arrive (returns.receive). Stock does not change. */
    public function receive(OrderReturn $orderReturn, MoveReturn $moveReturn): ReturnResource
    {
        return new ReturnResource(ReturnController::loadForDisplay($moveReturn->handle($orderReturn, ReturnStatus::Received)));
    }

    /**
     * Restock decisions for lines of a Received Return (returns.receive). Refused with 409
     * `return_not_received` or `restock_already_decided`.
     */
    public function restock(RestockReturnRequest $request, OrderReturn $orderReturn, DecideRestock $decideRestock): ReturnResource
    {
        return new ReturnResource(ReturnController::loadForDisplay($decideRestock->handle($orderReturn, $request->decisions())));
    }

    /**
     * Received → Completed with its Refund (refunds.issue). Refused with 409
     * `restock_decisions_pending`, `shipping_already_refunded` or `refund_exceeds_cap`.
     */
    public function refund(RefundReturnRequest $request, OrderReturn $orderReturn, RefundReturn $refundReturn): ReturnResource
    {
        return new ReturnResource(ReturnController::loadForDisplay(
            $refundReturn->handle($orderReturn, $request->amount(), $request->refundShipping(), $request->idempotencyKey()),
        ));
    }
}
