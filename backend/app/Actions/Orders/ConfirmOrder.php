<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\CouponRedemptions;
use App\Services\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a Pending Order: redeems its Coupon, if any, and reserves every line in its
 * Fulfilling warehouse, or nothing. The Order's row is locked first, then its Coupon's,
 * then the Variants and balances (ADR 0008), so simultaneous confirmations for the last
 * units (or the last use of a Coupon) never oversell: exactly what is left succeeds and
 * the rest are refused.
 */
final class ConfirmOrder
{
    public function __construct(
        private readonly MoveOrderStatus $moveOrderStatus,
        private readonly CouponRedemptions $couponRedemptions,
        private readonly Stock $stock,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition` unless Pending, 422
     *                               `coupon_invalid` when its Coupon no longer applies,
     *                               409 `insufficient_stock` naming the lines that lack stock
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            [$order] = $this->moveOrderStatus->handle($order, OrderStatus::Confirmed);

            $this->couponRedemptions->redeem($order);
            $this->stock->reserve($order, $order->warehouse_id, $order->lines
                ->filter(fn (OrderLine $line): bool => $line->quantity > 0)
                ->mapWithKeys(fn (OrderLine $line): array => [$line->variant_id => $line->quantity])
                ->all());

            return $order;
        });
    }
}
