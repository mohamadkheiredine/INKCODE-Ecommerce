<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a Pending Order: reserves every line in its Fulfilling warehouse, or nothing.
 * The Order's row is locked first, then the Variants and balances (ADR 0008), so
 * simultaneous confirmations for the last units never oversell: exactly the Available
 * quantity succeeds and the rest are refused.
 */
final class ConfirmOrder
{
    public function __construct(
        private readonly MoveOrderStatus $moveOrderStatus,
        private readonly Stock $stock,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition` unless Pending, 409
     *                               `insufficient_stock` naming the lines that lack stock
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            [$order] = $this->moveOrderStatus->handle($order, OrderStatus::Confirmed);

            $this->stock->reserve($order, $order->warehouse_id, $order->lines
                ->mapWithKeys(fn (OrderLine $line): array => [$line->variant_id => $line->quantity])
                ->all());

            return $order;
        });
    }
}
