<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOutcome;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * The one writer of the Payment ledger. Each row it adds is audited, and the Order's
 * Payment status, Captured amount and Refunds total are recomputed from the ledger right
 * after, in the caller's transaction (which holds the Order's row lock).
 *
 *     $ledger->add($order, PaymentKind::Payment, PaymentMethod::Manual, '20.00', PaymentOutcome::Succeeded);
 *     $ledger->settle($order); // the Order's total went down: refund what it now holds beyond it
 */
final class PaymentLedger
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    public function add(
        Order $order,
        PaymentKind $kind,
        PaymentMethod $method,
        string $amount,
        PaymentOutcome $status,
        ?string $gatewayReference = null,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): Payment {
        $userId = $this->auth->guard()->id();
        $payment = $order->payments()->create([
            'kind' => $kind,
            'method' => $method,
            'amount' => $amount,
            'status' => $status,
            'gateway_reference' => $gatewayReference,
            'note' => $note,
            'user_id' => $userId === null ? null : (int) $userId,
            'idempotency_key' => $idempotencyKey,
        ]);
        $payment->setRelation('order', $order);
        $this->auditTrail->created($payment);
        $this->recompute($order);

        return $payment;
    }

    /**
     * Gives back, as one Refund, what the Order now holds beyond what it owes (after it
     * was cancelled or reduced), by the Payment method of its latest successful payment;
     * then its Payment status is recomputed. Returns the Refund, or null when none is due.
     */
    public function settle(Order $order, ?string $note = null): ?Payment
    {
        $this->recompute($order);
        $excess = bcsub($order->netCaptured(), $order->amountOwed(), 2);
        if (bccomp($excess, '0', 2) <= 0) {
            return null;
        }

        $lastPayment = $order->payments()
            ->where(['kind' => PaymentKind::Payment, 'status' => PaymentOutcome::Succeeded])
            ->latest('id')
            ->firstOrFail();

        return $this->refund($order, $lastPayment->method, $excess, $note);
    }

    /**
     * Records a Refund, through the gateway for a Card. The caller has checked the cap.
     *
     * @throws BusinessRuleViolation 409 `refund_method_unavailable` for a Card Refund on an Order with no approved Card charge
     */
    public function refund(Order $order, PaymentMethod $method, string $amount, ?string $note = null, ?string $idempotencyKey = null): Payment
    {
        $reference = null;
        if ($method === PaymentMethod::Card) {
            $charge = $order->payments()
                ->where(['kind' => PaymentKind::Payment, 'method' => PaymentMethod::Card, 'status' => PaymentOutcome::Succeeded])
                ->latest('id')
                ->first()
                ?? throw new BusinessRuleViolation('refund_method_unavailable', __('payments.refund_method_unavailable', ['number' => $order->number]));
            $reference = $this->gateway->refund((string) $charge->gateway_reference, $amount, $order->currency_code)->reference;
        }

        return $this->add($order, PaymentKind::Refund, $method, $amount, PaymentOutcome::Succeeded, $reference, $note, $idempotencyKey);
    }

    /**
     * Sums the Order's successful ledger rows into its Captured amount, Refunds total and
     * Payment status.
     */
    public function recompute(Order $order): void
    {
        $totals = $order->payments()
            ->where('status', PaymentOutcome::Succeeded)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'payment' THEN amount END), 0) AS captured")
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'refund' THEN amount END), 0) AS refunded")
            ->first();
        $captured = bcadd((string) ($totals->captured ?? '0'), '0', 2);
        $refunded = bcadd((string) ($totals->refunded ?? '0'), '0', 2);

        $order->update([
            'captured_amount' => $captured,
            'refunded_amount' => $refunded,
            'payment_status' => PaymentStatus::derive($captured, $refunded, $order->amountOwed()),
        ]);
    }
}
