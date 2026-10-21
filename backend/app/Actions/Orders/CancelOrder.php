<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\OrderStock;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a whole Order before it is Shipped, with an optional reason kept in its status
 * history. In the same transaction an Order still holding Reserved units releases them,
 * and one past the Deduction stage puts its units back On-hand ({@see OrderStock}).
 */
final class CancelOrder
{
    public function __construct(
        private readonly MoveOrderStatus $moveOrderStatus,
        private readonly OrderStock $orderStock,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition` once Shipped or Cancelled
     */
    public function handle(Order $order, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $note): Order {
            [$order, $from] = $this->moveOrderStatus->handle($order, OrderStatus::Cancelled, $note);

            $this->orderStock->adjust($order, $from, $order->lines
                ->mapWithKeys(fn (OrderLine $line): array => [$line->variant_id => -$line->quantity])
                ->all(), $note);

            return $order;
        });
    }
}
