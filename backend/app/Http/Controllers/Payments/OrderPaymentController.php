<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\RecordPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

/**
 * Adds a payment to an Order's Payment ledger. Answers with the Order (its new Payment
 * status and ledger) and the row just recorded as `payment`. Safe to retry with an
 * `Idempotency-Key` header: a replay records nothing and answers the same. Refunds:
 * {@see OrderRefundController}.
 */
class OrderPaymentController extends Controller
{
    /**
     * Records a payment. A declined Card charge is still recorded (status `failed`) and
     * answered with 201; a payment above the amount still due is refused with 409
     * `payment_exceeds_due`.
     */
    public function store(StorePaymentRequest $request, Order $order, RecordPayment $recordPayment): JsonResponse
    {
        $payment = $recordPayment->handle(
            $order,
            $request->paymentMethod(),
            $request->amount(),
            $request->cardOutcome(),
            $request->note(),
            $request->idempotencyKey(),
        );

        return (new OrderResource($order->refresh()->loadForDisplay()))
            ->additional(['payment' => new PaymentResource($payment->load('user:id,name'))])
            ->response()
            ->setStatusCode(201);
    }
}
