<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\CardTestOutcome;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOutcome;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\CardPaymentDeclined;
use App\Services\PaymentLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Records a payment on an Order: a Cash on delivery collection, a Manual payment, or a
 * Card charge through the Payment gateway. A declined charge is kept in the ledger as
 * Failed (it counts toward nothing) and every active Finance User is told through the bell.
 */
final class RecordPayment
{
    public function __construct(
        private readonly PaymentLedger $ledger,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @param  string  $amount  decimal string, more than zero
     *
     * @throws BusinessRuleViolation 409 `payment_exceeds_due` above the amount still due
     */
    public function handle(Order $order, PaymentMethod $method, string $amount, ?CardTestOutcome $cardOutcome, ?string $note, ?string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($order, $method, $amount, $cardOutcome, $note, $idempotencyKey): Payment {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $due = $order->amountDue();
            if (bccomp($amount, $due, 2) > 0) {
                throw new BusinessRuleViolation('payment_exceeds_due', __('payments.exceeds_due', ['number' => $order->number, 'due' => $due]), 409, ['amount_due' => $due]);
            }

            if ($method !== PaymentMethod::Card) {
                return $this->ledger->add($order, PaymentKind::Payment, $method, $amount, PaymentOutcome::Succeeded, null, $note, $idempotencyKey);
            }

            $result = $this->gateway->charge($amount, $order->currency_code, $cardOutcome ?? CardTestOutcome::Approve);
            $payment = $this->ledger->add(
                $order,
                PaymentKind::Payment,
                $method,
                $amount,
                $result->approved ? PaymentOutcome::Succeeded : PaymentOutcome::Failed,
                $result->reference,
                $note,
                $idempotencyKey,
            );

            if (! $result->approved) {
                Notification::send(
                    User::query()
                        ->where('is_active', true)
                        ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Finance->value))
                        ->get(),
                    new CardPaymentDeclined($order->id, $order->number, $amount),
                );
            }

            return $payment;
        });
    }
}
