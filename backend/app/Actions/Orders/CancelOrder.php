<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\Settings;
use App\Services\Stock;
use App\Services\StockChange;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a whole Order before it is Shipped, with an optional reason kept in its status
 * history. An Order still holding Reserved units releases them in the same transaction.
 * (Units already deducted at the Deduction stage come back with Shipments, ticket #18.)
 */
final class CancelOrder
{
    public function __construct(
        private readonly MoveOrderStatus $moveOrderStatus,
        private readonly Stock $stock,
        private readonly Settings $settings,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition` once Shipped or Cancelled
     */
    public function handle(Order $order, ?string $note): Order
    {
        return DB::transaction(function () use ($order, $note): Order {
            [$order, $from] = $this->moveOrderStatus->handle($order, OrderStatus::Cancelled, $note);

            if ($from->holdsReservation($this->settings->deductionStage())) {
                $this->stock->move(StockMovementType::Release, $order, $order->lines
                    ->map(fn (OrderLine $line): StockChange => new StockChange($line->variant_id, $order->warehouse_id, reservedDelta: -$line->quantity))
                    ->values()
                    ->all(), $note);
            }

            return $order;
        });
    }
}
