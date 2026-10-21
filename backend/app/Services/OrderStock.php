<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;

/**
 * What an Order's stock does when its quantities change, it reaches the Deduction stage or
 * it ships, through {@see Stock} (so inside the caller's transaction, under the same locks).
 * Each line counts the units that left On-hand (`deducted_quantity`) and the units shipped
 * (`shipped_quantity`), so whatever the Deduction stage is when something happens, a unit
 * is deducted exactly once:
 *
 * - Pending: nothing is reserved yet, so nothing moves.
 * - Confirmed onward, a line's units not yet deducted are Reserved.
 * - Reaching the Deduction stage (Preparing or Ready for shipping) deducts every unit left.
 * - A Shipment deducts only the units it carries that were not deducted already.
 * - Cancelled units are released when Reserved and put back On-hand when deducted.
 */
final class OrderStock
{
    public function __construct(
        private readonly Stock $stock,
        private readonly Settings $settings,
    ) {}

    /**
     * A Pending or Confirmed Order's quantities changed (an edit). A Confirmed Order reserves
     * more units or releases fewer; it has deducted nothing yet (the Deduction stage is
     * Preparing at the earliest).
     *
     * @param  array<int, int>  $deltas  signed change in units per Variant id
     *
     * @throws BusinessRuleViolation 409 `insufficient_stock` when more units cannot be reserved
     */
    public function adjust(Order $order, OrderStatus $status, array $deltas): void
    {
        if ($status !== OrderStatus::Confirmed) {
            return;
        }

        $more = array_filter($deltas, fn (int $delta): bool => $delta > 0);
        $fewer = array_filter($deltas, fn (int $delta): bool => $delta < 0);

        if ($fewer !== []) {
            $this->stock->move(StockMovementType::Release, $order, array_map(
                fn (int $variantId, int $delta): StockChange => new StockChange($variantId, $order->warehouse_id, reservedDelta: $delta),
                array_keys($fewer),
                $fewer,
            ));
        }
        if ($more !== []) {
            $this->stock->reserve($order, $order->warehouse_id, $more);
        }
    }

    /**
     * Units of an Order in status `$status` are cancelled. Call it before the lines'
     * quantities go down: the units still Reserved are released first, then deducted ones
     * go back On-hand. Shipped units cannot be cancelled.
     *
     * @param  array<int, int>  $units  units cancelled per Order line id
     */
    public function cancel(Order $order, OrderStatus $status, array $units, ?string $reason = null): void
    {
        if (in_array($status, [OrderStatus::Pending, OrderStatus::Cancelled], true)) {
            return;
        }

        $released = [];
        $returned = [];
        foreach ($order->lines->whereIn('id', array_keys($units)) as $line) {
            $cancelled = $units[$line->id];
            $release = min($cancelled, $line->quantity - $line->deducted_quantity);
            $return = $cancelled - $release;

            if ($release > 0) {
                $released[] = new StockChange($line->variant_id, $order->warehouse_id, reservedDelta: -$release);
            }
            if ($return > 0) {
                $returned[] = new StockChange($line->variant_id, $order->warehouse_id, onHandDelta: $return);
                $line->update(['deducted_quantity' => $line->deducted_quantity - $return]);
            }
        }

        if ($released !== []) {
            $this->stock->move(StockMovementType::Release, $order, $released, $reason);
        }
        if ($returned !== []) {
            $this->stock->move(StockMovementType::CancellationReturn, $order, $returned, $reason);
        }
    }

    /**
     * The Order moved to `$status`. When that is at or past the Deduction stage, every
     * Reserved unit not yet deducted leaves On-hand.
     */
    public function reached(Order $order, OrderStatus $status): void
    {
        $flow = OrderStatus::flow();
        $stage = OrderStatus::from($this->settings->deductionStage()->value);

        if (array_search($status, $flow, true) >= array_search($stage, $flow, true)) {
            $this->deduct($order, $order->lines
                ->mapWithKeys(fn (OrderLine $line): array => [$line->id => $line->quantity - $line->deducted_quantity])
                ->all());
        }
    }

    /**
     * A Shipment carries these units: each line deducts what it had not deducted already,
     * then counts them as shipped.
     *
     * @param  array<int, int>  $units  units shipped per Order line id
     */
    public function ship(Order $order, array $units): void
    {
        $lines = $order->lines->whereIn('id', array_keys($units));

        $this->deduct($order, $lines
            ->mapWithKeys(fn (OrderLine $line): array => [
                $line->id => max(0, $units[$line->id] - ($line->deducted_quantity - $line->shipped_quantity)),
            ])
            ->all());

        foreach ($lines as $line) {
            $line->update(['shipped_quantity' => $line->shipped_quantity + $units[$line->id]]);
        }
    }

    /**
     * Reserved units leave On-hand, and the lines count them as deducted.
     *
     * @param  array<int, int>  $units  units per Order line id
     */
    private function deduct(Order $order, array $units): void
    {
        $lines = $order->lines->whereIn('id', array_keys(array_filter($units, fn (int $quantity): bool => $quantity > 0)));
        if ($lines->isEmpty()) {
            return;
        }

        $this->stock->move(StockMovementType::Deduction, $order, $lines
            ->map(fn (OrderLine $line): StockChange => new StockChange($line->variant_id, $order->warehouse_id, onHandDelta: -$units[$line->id], reservedDelta: -$units[$line->id]))
            ->values()
            ->all());

        foreach ($lines as $line) {
            $line->update(['deducted_quantity' => $line->deducted_quantity + $units[$line->id]]);
        }
    }
}
