<?php

namespace App\Actions\Orders;

use App\Actions\Shipping\QuoteShipping;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Variant;
use App\Services\AppliedCoupon;
use App\Services\AuditTrail;
use App\Services\CouponRedemptions;
use App\Services\CouponValidator;
use App\Services\OrderPricing;
use App\Services\OrderStock;
use App\Services\PaymentLedger;
use Illuminate\Support\Facades\DB;

/**
 * Changes a Pending or Confirmed Order's lines, quantities, addresses and Shipping method.
 * Lines already on the Order keep their snapshot (name, SKU, unit price); new lines are
 * priced from the Variant now; every line is re-priced at the Order's own VAT rate. A
 * Confirmed Order reserves the extra units and releases the removed ones in the same
 * transaction, all or nothing; money received beyond a lower new total is given back as a
 * Refund. From Preparing onward it is a Locked order.
 */
final class UpdateOrder
{
    public function __construct(
        private readonly QuoteShipping $quoteShipping,
        private readonly OrderPricing $pricing,
        private readonly OrderStock $orderStock,
        private readonly CouponValidator $couponValidator,
        private readonly CouponRedemptions $couponRedemptions,
        private readonly AuditTrail $auditTrail,
        private readonly PaymentLedger $paymentLedger,
    ) {}

    /**
     * @param  array{billing_address_id: int, shipping_address_id: int, shipping_method_id: int, coupon_code?: string|null, lines: list<array{variant_id: int, quantity: int}>}  $values
     *
     * @throws BusinessRuleViolation 409 `order_locked` from Preparing onward, 409
     *                               `insufficient_stock` when the extra units are not
     *                               Available, 422 `shipping_method_unavailable`,
     *                               422 `coupon_invalid` when the Coupon does not apply
     */
    public function handle(Order $order, array $values): Order
    {
        return DB::transaction(function () use ($order, $values): Order {
            $order = self::lockEditable($order);
            $before = $order->auditValues();

            $billing = CustomerAddress::query()->findOrFail($values['billing_address_id']);
            $shipping = CustomerAddress::query()->findOrFail($values['shipping_address_id']);
            $shippingTotal = $order->shipping_total;
            $methodName = $order->shipping_method_name;
            // The method and its price are quoted again only when the method or the place it
            // delivers to changes; otherwise the Order keeps the price it was sold with.
            $movesTo = [$shipping->country_code, $shipping->city];
            if ($values['shipping_method_id'] !== $order->shipping_method_id || $movesTo !== [$order->shipping_address['country_code'], $order->shipping_address['city']]) {
                $method = $this->quoteShipping->handle($shipping->country_code, $shipping->city)->method($values['shipping_method_id']);
                $shippingTotal = $method->price;
                $methodName = $method->name;
            }

            $wanted = array_column($values['lines'], 'quantity', 'variant_id');
            $existing = $order->lines->keyBy('variant_id');
            $newVariants = Variant::query()
                ->with(['product:id,name,taxable', 'attributeValues.attribute:id,name'])
                ->findMany(array_diff(array_keys($wanted), $existing->keys()->all()))
                ->keyBy('id');

            $deltas = [];
            $snapshots = [];
            foreach ($existing as $variantId => $line) {
                $quantity = $wanted[$variantId] ?? 0;
                $deltas[$variantId] = $quantity - $line->quantity;
                if ($quantity > 0 || $line->cancelled_quantity > 0) {
                    $snapshots[] = [
                        'variant_id' => $line->variant_id,
                        'product_name' => $line->product_name,
                        'sku' => $line->sku,
                        'options' => $line->options,
                        'quantity' => $quantity,
                        'unit_price' => $line->unit_price,
                        'taxable' => $line->taxable,
                    ];
                } else {
                    $line->delete();
                }
            }
            foreach ($newVariants as $variantId => $variant) {
                $deltas[$variantId] = $wanted[$variantId];
                $snapshots[] = OrderPricing::snapshot($variant, $wanted[$variantId]);
            }

            // The Coupon is checked again against the new lines; a Confirmed Order's Coupon row
            // stays locked until its redemption is updated below.
            $couponCode = $values['coupon_code'] ?? null;
            $applied = $couponCode === null ? null : $this->couponValidator->validate(
                $couponCode,
                array_map(fn (array $snapshot): array => [
                    'variant_id' => $snapshot['variant_id'],
                    'quantity' => $snapshot['quantity'],
                    'unit_price' => $snapshot['unit_price'],
                ], $snapshots),
                $order->customer_id,
                $order,
                lock: $order->status !== OrderStatus::Pending,
            );
            foreach ($snapshots as $index => $snapshot) {
                $snapshots[$index]['coupon_eligible'] = $applied?->isEligible($snapshot['variant_id']) ?? false;
            }

            $priced = $this->pricing->reprice($snapshots, $order->vat_rate, $shippingTotal, $applied?->terms());
            // Reserve or release first: it locks the Variants, and lines referencing them may
            // only be written after that (ADR 0008), or two edits of the same Variant deadlock.
            $this->orderStock->adjust($order, $order->status, $deltas);
            foreach ($snapshots as $index => $snapshot) {
                $order->lines()->updateOrCreate(['variant_id' => $snapshot['variant_id']], [...$snapshot, ...$priced['lines'][$index]]);
            }
            $order->update([
                ...($applied?->orderSnapshot() ?? AppliedCoupon::none()),
                'billing_address' => CreateOrder::snapshot($billing),
                'shipping_address' => CreateOrder::snapshot($shipping),
                'shipping_method_id' => $values['shipping_method_id'],
                'shipping_method_name' => $methodName,
                ...array_intersect_key($priced, array_flip(['items_total', 'discount_total', 'vat_total', 'shipping_total', 'total'])),
            ]);

            $this->couponRedemptions->sync($order);
            $this->auditTrail->updated($order->load('lines'), $before);
            $this->paymentLedger->settle($order);

            return $order;
        });
    }

    /**
     * Locks the Order's row for the rest of the transaction and refuses a Locked order.
     *
     * @throws BusinessRuleViolation 409 `order_locked`
     */
    public static function lockEditable(Order $order): Order
    {
        $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

        if (! $order->status->isEditable()) {
            throw new BusinessRuleViolation('order_locked', __('orders.locked', [
                'number' => $order->number,
                'status' => __("orders.statuses.{$order->status->value}"),
            ]));
        }

        return $order;
    }
}
