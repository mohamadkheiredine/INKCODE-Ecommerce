<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;

/**
 * What an Order's stock does when its quantities change or it reaches the Deduction stage,
 * through {@see Stock} (so inside the caller's transaction, under the same locks):
 *
 * - Pending: nothing is reserved yet, so nothing moves.
 * - Confirmed until the Deduction stage: more units are reserved, fewer are released.
 * - From the Deduction stage until Shipped: fewer units go back On-hand (they never left).
 */
final class OrderStock
{
    public function __construct(
        private readonly Stock $stock,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<int, int>  $deltas  signed change in units per Variant id
     *
     * @throws BusinessRuleViolation 409 `insufficient_stock` when more units cannot be reserved
     */
    public function adjust(Order $order, OrderStatus $status, array $deltas, ?string $reason = null): void
    {
        $deltas = array_filter($deltas, fn (int $delta): bool => $delta !== 0);
        $stage = $this->settings->deductionStage();

        if ($status->holdsReservation($stage)) {
            $more = array_filter($deltas, fn (int $delta): bool => $delta > 0);
            $fewer = array_filter($deltas, fn (int $delta): bool => $delta < 0);

            if ($fewer !== []) {
                $this->stock->move(StockMovementType::Release, $order, $this->changes($order, $fewer, onHand: false), $reason);
            }
            if ($more !== []) {
                $this->stock->reserve($order, $order->warehouse_id, $more);
            }
        } elseif ($status->holdsDeductedUnits($stage)) {
            $fewer = array_map(fn (int $delta): int => -$delta, array_filter($deltas, fn (int $delta): bool => $delta < 0));

            if ($fewer !== []) {
                $this->stock->move(StockMovementType::CancellationReturn, $order, $this->changes($order, $fewer, onHand: true), $reason);
            }
        }
    }

    /**
     * The Order reached the Deduction stage: its Reserved units leave On-hand.
     */
    public function deduct(Order $order): void
    {
        $quantities = $order->lines
            ->filter(fn (OrderLine $line): bool => $line->quantity > 0)
            ->mapWithKeys(fn (OrderLine $line): array => [$line->variant_id => $line->quantity])
            ->all();

        if ($quantities !== []) {
            $this->stock->move(StockMovementType::Deduction, $order, array_map(
                fn (int $variantId, int $quantity): StockChange => new StockChange($variantId, $order->warehouse_id, onHandDelta: -$quantity, reservedDelta: -$quantity),
                array_keys($quantities),
                $quantities,
            ));
        }
    }

    /**
     * Units released from Reserved (negative deltas) or put back On-hand (positive ones).
     *
     * @param  array<int, int>  $deltas
     * @return list<StockChange>
     */
    private function changes(Order $order, array $deltas, bool $onHand): array
    {
        return array_map(
            fn (int $variantId, int $delta): StockChange => $onHand
                ? new StockChange($variantId, $order->warehouse_id, onHandDelta: $delta)
                : new StockChange($variantId, $order->warehouse_id, reservedDelta: $delta),
            array_keys($deltas),
            $deltas,
        );
    }
}
