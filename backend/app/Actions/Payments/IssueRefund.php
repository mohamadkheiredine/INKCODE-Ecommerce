<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentLedger;
use Illuminate\Support\Facades\DB;

/**
 * Gives money back to the Customer on an Order. All Refunds together can never exceed the
 * Captured amount: the most one may be is the Captured amount minus earlier Refunds.
 */
final class IssueRefund
{
    public function __construct(private readonly PaymentLedger $ledger) {}

    /**
     * @param  string  $amount  decimal string, more than zero
     *
     * @throws BusinessRuleViolation 409 `refund_exceeds_captured` above the refundable amount,
     *                               409 `refund_method_unavailable` for a Card Refund without an approved Card charge
     */
    public function handle(Order $order, PaymentMethod $method, string $amount, ?string $note, ?string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($order, $method, $amount, $note, $idempotencyKey): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $refundable = $order->netCaptured();
            if (bccomp($amount, $refundable, 2) > 0) {
                throw new BusinessRuleViolation('refund_exceeds_captured', __('payments.refund_exceeds_captured', ['number' => $order->number, 'refundable' => $refundable]), 409, ['refundable' => $refundable]);
            }

            return $this->ledger->refund($order, $method, $amount, $note, $idempotencyKey);
        });
    }
}
