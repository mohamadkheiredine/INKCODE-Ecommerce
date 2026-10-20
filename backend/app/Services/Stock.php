<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock changes (ADR 0008). Every operation runs in one transaction that
 * locks, always in this order: the Warehouses receiving units (shared), the Variants (by
 * id), then their balance rows (by id). It then checks the rules (On-hand and Reserved
 * never negative, Reserved never above On-hand), updates the balances and appends one
 * Stock movement per change, so every balance always equals the sum of its movements.
 *
 *     $stock->receive($receipt);
 *     $stock->move(StockMovementType::Reservation, $order, [new StockChange($variantId, $warehouseId, reservedDelta: 2)]);
 */
final class Stock
{
    /** Average cost keeps four decimals (a hundredth of a cent); intermediate maths keeps more. */
    private const int COST_SCALE = 4;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * Applies the changes as one unit: all of them or none.
     *
     * @param  Model  $source  the record that caused the changes (a Receipt, an Order…)
     * @param  list<StockChange>  $changes
     * @return list<StockMovement> one per change, in the given order
     *
     * @throws BusinessRuleViolation 409 `insufficient_stock` when a balance would go
     *                               negative or reserve more than is on hand, 409
     *                               `warehouse_inactive` when units would arrive in a
     *                               deactivated Warehouse
     */
    public function move(StockMovementType $type, Model $source, array $changes, ?string $reason = null): array
    {
        return DB::transaction(function () use ($type, $source, $changes, $reason): array {
            $this->lockReceivingWarehouses(array_map(
                fn (StockChange $change): int => $change->warehouseId,
                array_filter($changes, fn (StockChange $change): bool => $change->onHandDelta > 0),
            ));
            $this->lockVariants(array_map(fn (StockChange $change): int => $change->variantId, $changes));
            $balances = $this->lockBalances($changes);

            $movements = [];
            foreach ($changes as $change) {
                $balance = $balances[$this->balanceKey($change->variantId, $change->warehouseId)];
                $onHand = $balance->on_hand + $change->onHandDelta;
                $reserved = $balance->reserved + $change->reservedDelta;

                if ($onHand < 0 || $reserved < 0 || $reserved > $onHand) {
                    throw $this->insufficientStock($balance);
                }

                $balance->update(['on_hand' => $onHand, 'reserved' => $reserved]);
                $movements[] = StockMovement::query()->create([
                    'variant_id' => $change->variantId,
                    'warehouse_id' => $change->warehouseId,
                    'type' => $type,
                    'on_hand_delta' => $change->onHandDelta,
                    'reserved_delta' => $change->reservedDelta,
                    'on_hand_after' => $onHand,
                    'reserved_after' => $reserved,
                    'reason' => $reason,
                    'user_id' => $this->auth->guard()->id(),
                    'source_type' => $source->getMorphClass(),
                    'source_id' => $source->getKey(),
                ]);
            }

            return $movements;
        });
    }

    /**
     * Saves the lines on a new Receipt, puts them into its Warehouse's On-hand and
     * re-weights each Variant's Average cost (ADR 0005):
     * ((On-hand in every Warehouse × old cost) + (received × unit cost)) ÷ new On-hand.
     * Each cost change is recorded in the audit trail.
     *
     * The lines are saved only once the Variants are locked: inserting them first would
     * take shared foreign-key locks on the Variants, and two Receipts upgrading those to
     * exclusive locks would deadlock.
     *
     * @param  list<array{variant_id: int, quantity: int, unit_cost: string}>  $lines  each Variant once
     *
     * @throws BusinessRuleViolation 409 `warehouse_inactive`
     */
    public function receive(Receipt $receipt, array $lines): void
    {
        DB::transaction(function () use ($receipt, $lines): void {
            $this->lockReceivingWarehouses([$receipt->warehouse_id]);
            $variants = $this->lockVariants(array_column($lines, 'variant_id'));
            $lines = $receipt->lines()->createMany($lines);

            /** @var array<int, int> $onHandByVariant */
            $onHandByVariant = StockBalance::query()
                ->whereIn('variant_id', $variants->modelKeys())
                ->groupBy('variant_id')
                ->selectRaw('variant_id, SUM(on_hand) AS on_hand')
                ->pluck('on_hand', 'variant_id')
                ->map(fn (mixed $onHand): int => (int) $onHand)
                ->all();

            foreach ($lines as $line) {
                $variant = $variants->findOrFail($line->variant_id);
                $before = $variant->auditValues();
                $variant->update(['average_cost' => $this->reweightedCost($variant, $onHandByVariant[$variant->id] ?? 0, $line)]);
                $this->auditTrail->updated($variant, $before);
            }

            $this->move(StockMovementType::Receipt, $receipt, $lines
                ->map(fn (ReceiptLine $line): StockChange => new StockChange($line->variant_id, $receipt->warehouse_id, onHandDelta: $line->quantity))
                ->values()
                ->all());
        });
    }

    private function reweightedCost(Variant $variant, int $onHand, ReceiptLine $line): string
    {
        if ($onHand <= 0) {
            return $line->unit_cost;
        }

        $value = bcadd(bcmul((string) $onHand, $variant->average_cost, 10), bcmul((string) $line->quantity, $line->unit_cost, 10), 10);
        $cost = bcdiv($value, (string) ($onHand + $line->quantity), 10);

        // Round half up to four decimals (costs are never negative).
        return bcadd($cost, '0.00005', self::COST_SCALE);
    }

    /**
     * Units may only arrive in an active Warehouse. The shared lock holds off a concurrent
     * deactivation (which locks the row for update) until this transaction ends.
     *
     * @param  array<int>  $warehouseIds
     */
    private function lockReceivingWarehouses(array $warehouseIds): void
    {
        if ($warehouseIds === []) {
            return;
        }

        $inactive = Warehouse::query()
            ->whereKey(array_values(array_unique($warehouseIds)))
            ->orderBy('id')
            ->sharedLock()
            ->get()
            ->first(fn (Warehouse $warehouse): bool => ! $warehouse->is_active);

        if ($inactive !== null) {
            throw new BusinessRuleViolation('warehouse_inactive', __('inventory.warehouses.inactive', ['name' => $inactive->name]));
        }
    }

    /**
     * Locking the Variants first serializes every stock operation on them, so a missing
     * balance row can be created without two transactions racing to insert it.
     *
     * @param  list<int>  $variantIds
     * @return Collection<int, Variant>
     */
    private function lockVariants(array $variantIds): Collection
    {
        return Variant::query()
            ->whereKey(array_values(array_unique($variantIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * The balance rows the changes touch, locked by id; missing rows start at zero.
     *
     * @param  list<StockChange>  $changes
     * @return array<string, StockBalance> keyed by {@see balanceKey()}
     */
    private function lockBalances(array $changes): array
    {
        $variantIds = array_values(array_unique(array_map(fn (StockChange $change): int => $change->variantId, $changes)));
        $warehouseIds = array_values(array_unique(array_map(fn (StockChange $change): int => $change->warehouseId, $changes)));

        $balances = StockBalance::query()
            ->whereIn('variant_id', $variantIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (StockBalance $balance): string => $this->balanceKey($balance->variant_id, $balance->warehouse_id))
            ->all();

        foreach ($changes as $change) {
            $key = $this->balanceKey($change->variantId, $change->warehouseId);
            $balances[$key] ??= StockBalance::query()->create([
                'variant_id' => $change->variantId,
                'warehouse_id' => $change->warehouseId,
                'on_hand' => 0,
                'reserved' => 0,
            ]);
        }

        return $balances;
    }

    private function balanceKey(int $variantId, int $warehouseId): string
    {
        return "$variantId:$warehouseId";
    }

    private function insufficientStock(StockBalance $balance): BusinessRuleViolation
    {
        return new BusinessRuleViolation('insufficient_stock', __('inventory.stock.insufficient', [
            'sku' => $balance->variant->sku,
            'warehouse' => $balance->warehouse->name,
            'available' => $balance->on_hand - $balance->reserved,
        ]));
    }
}
