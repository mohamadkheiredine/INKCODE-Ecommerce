<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\CouponRedemptions;
use App\Services\OrderStock;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a whole Order before it is Shipped, with an optional reason kept in its status
 * history. In the same transaction an Order still holding Reserved units releases them,
 * and one past the Deduction stage puts its units back On-hand ({@see OrderStock}); its
 * Coupon's redemption is released, so the use counts towards the limits again.
 */
final class CancelOrder
{
    public function __construct(
        private readonly MoveOrderStatus $moveOrderStatus,
        private readonly OrderStock $orderStock,
        private readonly CouponRedemptions $couponRedemptions,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition` once Shipped or Cancelled, 409
     *                               `order_partly_shipped` once a Shipment left (cancel the rest by line)
     */
    public function handle(Order $order, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $note): Order {
            [$order, $from] = $this->moveOrderStatus->handle($order, OrderStatus::Cancelled, $note);
            if ($order->lines->sum('shipped_quantity') > 0) {
                throw new BusinessRuleViolation('order_partly_shipped', __('shipments.order_partly_shipped', ['number' => $order->number]));
            }

            $this->orderStock->cancel($order, $from, $order->lines
                ->mapWithKeys(fn (OrderLine $line): array => [$line->id => $line->quantity])
                ->all(), $note);
            $this->couponRedemptions->release($order);

            return $order;
        });
    }
}
