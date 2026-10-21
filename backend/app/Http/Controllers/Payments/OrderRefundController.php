<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\IssueRefund;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreRefundRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Adds a Refund to an Order's Payment ledger. Answers with the Order (its new Payment
 * status and ledger) and the Refund as `payment`. Safe to retry with an `Idempotency-Key`.
 */
class OrderRefundController extends Controller
{
    /**
     * Above the Captured amount minus earlier Refunds it is refused with 409
     * `refund_exceeds_captured`.
     */
    public function store(StoreRefundRequest $request, Order $order, IssueRefund $issueRefund): JsonResponse
    {
        $refund = $issueRefund->handle($order, $request->paymentMethod(), $request->amount(), $request->note(), $request->idempotencyKey());

        return (new OrderResource($order->refresh()->loadForDisplay()))
            ->additional(['payment' => new PaymentResource($refund->load('user:id,name'))])
            ->response()
            ->setStatusCode(201);
    }
}
