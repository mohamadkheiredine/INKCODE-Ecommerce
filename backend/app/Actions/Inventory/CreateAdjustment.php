<?php

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Adjustment;
use App\Services\AuditTrail;
use App\Services\Stock;
use App\Services\StockChange;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

/**
 * Records an Adjustment and applies it to the Warehouse's On-hand through the stock
 * service, which refuses to push On-hand below Reserved.
 */
final class CreateAdjustment
{
    public function __construct(
        private readonly Stock $stock,
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{warehouse_id: int, variant_id: int, quantity_delta: int, reason: AdjustmentReason, notes: string|null}  $values
     *
     * @throws BusinessRuleViolation 409 `insufficient_stock` or `warehouse_inactive`
     */
    public function handle(array $values): Adjustment
    {
        return DB::transaction(function () use ($values): Adjustment {
            $userId = $this->auth->guard()->id();
            $adjustment = Adjustment::query()->create([
                ...$values,
                'user_id' => $userId === null ? null : (int) $userId,
            ]);
            $adjustment->assignNumber();

            $this->stock->move(
                StockMovementType::Adjustment,
                $adjustment,
                [new StockChange($values['variant_id'], $values['warehouse_id'], onHandDelta: $values['quantity_delta'])],
                $values['reason']->value,
            );
            $this->auditTrail->created($adjustment);

            return $adjustment;
        });
    }
}
