<?php

namespace App\Actions\Returns;

use App\Enums\AuditAction;
use App\Enums\PaymentKind;
use App\Enums\PaymentOutcome;
use App\Enums\ReturnStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\AuditTrail;
use App\Services\PaymentLedger;
use Illuminate\Support\Facades\DB;

/**
 * Completes a Received Return whose lines all have their Restock decision, with its
 * Refund. The suggestion is what the Customer paid for the returned units (plus the
 * Order's shipping when ticked, once per Order); staff may lower it. The cap is the lower
 * of that suggestion and the Order's Captured amount minus earlier Refunds. The Refund is
 * a row of the Order's Payment ledger, by the Payment method of its latest successful
 * payment, linked to the Return; a zero amount completes it without a Refund.
 */
final class RefundReturn
{
    public function __construct(
        private readonly PaymentLedger $ledger,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  string  $amount  decimal string, zero or more
     *
     * @throws BusinessRuleViolation 409 `invalid_return_transition`, 409 `restock_decisions_pending`,
     *                               409 `shipping_already_refunded`, 409 `refund_exceeds_cap` (with `details.cap`)
     */
    public function handle(OrderReturn $return, string $amount, bool $refundShipping, ?string $idempotencyKey): OrderReturn
    {
        return DB::transaction(function () use ($return, $amount, $refundShipping, $idempotencyKey): OrderReturn {
            $order = Order::query()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            $return = OrderReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $return->setRelation('order', $order);
            $return->load('lines.orderLine');

            if (! $return->status->canMoveTo(ReturnStatus::Completed)) {
                throw MoveReturn::invalidTransition($return, ReturnStatus::Completed);
            }
            if (! $return->isFullyDecided()) {
                throw new BusinessRuleViolation('restock_decisions_pending', __('returns.decisions_pending', ['number' => $return->number]));
            }
            if ($refundShipping && $order->shippingRefunded()) {
                throw new BusinessRuleViolation('shipping_already_refunded', __('returns.shipping_already_refunded', ['number' => $order->number]));
            }

            $suggested = $return->suggestedRefund($refundShipping);
            $refundable = $order->netCaptured();
            $cap = bccomp($suggested, $refundable, 2) <= 0 ? $suggested : $refundable;
            if (bccomp($amount, $cap, 2) > 0) {
                throw new BusinessRuleViolation('refund_exceeds_cap', __('returns.refund_exceeds_cap', [
                    'number' => $return->number,
                    'cap' => $cap,
                ]), details: ['cap' => $cap, 'suggested' => $suggested, 'refundable' => $refundable]);
            }

            $refundId = null;
            if (bccomp($amount, '0', 2) > 0) {
                $method = $order->payments()
                    ->where(['kind' => PaymentKind::Payment, 'status' => PaymentOutcome::Succeeded])
                    ->latest('id')
                    ->firstOrFail()
                    ->method;
                $refundId = $this->ledger->refund($order, $method, $amount, __('returns.refund_note', ['number' => $return->number]), $idempotencyKey)->id;
            }

            $return->update([
                'status' => ReturnStatus::Completed,
                'refund_id' => $refundId,
                'refund_amount' => $amount,
                'refund_shipping' => $refundShipping,
                'completed_at' => now(),
            ]);
            $this->auditTrail->record(AuditAction::StatusChanged, $return, ['status' => ReturnStatus::Received->value], [
                'status' => ReturnStatus::Completed->value,
                'refund_id' => $refundId,
                'refund_amount' => $amount,
                'refund_shipping' => $refundShipping,
            ]);

            return $return;
        });
    }
}
