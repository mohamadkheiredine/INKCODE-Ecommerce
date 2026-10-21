<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\OrderPricing;
use App\Services\OrderStock;
use App\Services\PaymentLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Partial cancellation: cancels some units of some lines before the Order ships. The units
 * move to each line's `cancelled_quantity`, their stock is released (or put back On-hand
 * past the Deduction stage), and the Order is re-priced at its own VAT rate; shipping is
 * kept; money received beyond the new total is given back as a Refund in the same step.
 * The reason, when given, is added to the timeline as a note. Cancelling every
 * remaining unit cancels the whole Order instead ({@see CancelOrder}).
 */
final class CancelOrderLines
{
    public function __construct(
        private readonly CancelOrder $cancelOrder,
        private readonly AddOrderNote $addOrderNote,
        private readonly OrderPricing $pricing,
        private readonly OrderStock $orderStock,
        private readonly AuditTrail $auditTrail,
        private readonly PaymentLedger $paymentLedger,
    ) {}

    /**
     * @param  list<array{line_id: int, quantity: int}>  $cancellations  each line once
     *
     * @throws BusinessRuleViolation 409 `invalid_transition` once Shipped or Cancelled
     * @throws ValidationException when a quantity is more than the line still has
     */
    public function handle(Order $order, array $cancellations, User $user, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $cancellations, $user, $note): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->status->isCancellable()) {
                throw MoveOrderStatus::refusal($order, OrderStatus::Cancelled);
            }

            $lines = $order->lines->keyBy('id');
            foreach ($cancellations as $index => $cancellation) {
                $line = $lines[$cancellation['line_id']];
                if ($cancellation['quantity'] > $line->quantity) {
                    throw ValidationException::withMessages([
                        "lines.$index.quantity" => __('orders.cancel_more_than_ordered', ['sku' => $line->sku, 'quantity' => $line->quantity]),
                    ]);
                }
            }

            $cancelled = array_sum(array_column($cancellations, 'quantity'));
            if ($cancelled === $lines->sum('quantity')) {
                return $this->cancelOrder->handle($order, $note);
            }

            $before = $order->auditValues();
            $deltas = [];
            foreach ($cancellations as $cancellation) {
                $line = $lines[$cancellation['line_id']];
                $line->quantity -= $cancellation['quantity'];
                $line->cancelled_quantity += $cancellation['quantity'];
                $deltas[$line->variant_id] = -$cancellation['quantity'];
            }

            $lines = $lines->values();
            $priced = $this->pricing->reprice(
                $lines->map(fn (OrderLine $line): array => ['quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'taxable' => $line->taxable])->all(),
                $order->vat_rate,
                $order->shipping_total,
            );
            foreach ($lines as $index => $line) {
                $line->fill($priced['lines'][$index])->save();
            }
            $order->update(array_intersect_key($priced, array_flip(['items_total', 'discount_total', 'vat_total', 'total'])));

            $this->orderStock->adjust($order, $order->status, $deltas, $note);
            $this->auditTrail->updated($order->load('lines'), $before);
            $this->paymentLedger->settle($order, $note);
            if ($note !== null) {
                $this->addOrderNote->handle($order, $user, $note);
            }

            return $order;
        });
    }
}
