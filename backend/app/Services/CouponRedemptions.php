<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Support\Carbon;

/**
 * Counts an Order's use of its Coupon. A Pending Order uses none; Confirm redeems the
 * Coupon (checked again, with its row locked, so the last use goes to one Order only); a
 * full cancellation releases it; editing a Confirmed Order or cancelling some of its lines
 * moves the redemption to the new Coupon or updates its discount. Callers run these inside
 * their own transaction, after locking the Order's row.
 */
final class CouponRedemptions
{
    public function __construct(private readonly CouponValidator $validator) {}

    /**
     * @throws BusinessRuleViolation 422 `coupon_invalid` when the Coupon no longer applies
     */
    public function redeem(Order $order): void
    {
        if ($order->coupon_code === null) {
            return;
        }

        $applied = $this->validator->validate($order->coupon_code, self::linesOf($order), $order->customer_id, $order, lock: true);
        $this->record($order, $applied->coupon->id);
    }

    public function release(Order $order): void
    {
        CouponRedemption::query()
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->update(['released_at' => Carbon::now()]);
    }

    /**
     * After an edit or partial cancellation: a Confirmed (or later) Order's redemption
     * follows its Coupon and discount. Its Coupon was validated (and locked) by the caller.
     */
    public function sync(Order $order): void
    {
        if ($order->status === OrderStatus::Pending) {
            return;
        }

        if ($order->coupon_id === null) {
            $this->release($order);

            return;
        }

        $this->record($order, $order->coupon_id);
    }

    /**
     * The lines as {@see CouponValidator} checks them.
     *
     * @return list<array{variant_id: int, quantity: int, unit_price: string}>
     */
    public static function linesOf(Order $order): array
    {
        return $order->lines
            ->map(fn (OrderLine $line): array => ['variant_id' => $line->variant_id, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price])
            ->values()
            ->all();
    }

    private function record(Order $order, int $couponId): void
    {
        $redemption = CouponRedemption::query()->firstOrNew(['order_id' => $order->id]);
        if ($redemption->coupon_id !== $couponId || $redemption->released_at !== null) {
            $redemption->redeemed_at = Carbon::now();
        }

        $redemption->fill([
            'coupon_id' => $couponId,
            'customer_id' => $order->customer_id,
            'discount_amount' => $order->discount_total,
            'released_at' => null,
        ])->save();
    }
}
