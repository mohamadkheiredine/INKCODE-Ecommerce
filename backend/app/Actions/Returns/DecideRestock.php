<?php

namespace App\Actions\Returns;

use App\Enums\RestockDecision;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\OrderReturn;
use App\Models\ReturnLine;
use App\Services\AuditTrail;
use App\Services\Stock;
use App\Services\StockChange;
use Illuminate\Support\Facades\DB;

/**
 * Records the Restock decision for lines of a Received Return, once each: restocked units
 * go back into On-hand at the Order's Fulfilling warehouse through the stock service; a
 * Write-off changes no stock. This is the only point where a Return touches stock.
 */
final class DecideRestock
{
    public function __construct(
        private readonly Stock $stock,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array<int, RestockDecision>  $decisions  per Return line id
     *
     * @throws BusinessRuleViolation 409 `return_not_received`, 409 `restock_already_decided`
     */
    public function handle(OrderReturn $return, array $decisions): OrderReturn
    {
        return DB::transaction(function () use ($return, $decisions): OrderReturn {
            $return = OrderReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $return->load(['lines.orderLine', 'order']);

            if ($return->status !== ReturnStatus::Received) {
                throw new BusinessRuleViolation('return_not_received', __('returns.not_received', ['number' => $return->number]));
            }

            $before = $return->auditValues();
            $changes = [];
            foreach ($decisions as $lineId => $decision) {
                /** @var ReturnLine $line */
                $line = $return->lines->firstOrFail('id', $lineId);
                if ($line->restock_decision !== null) {
                    throw new BusinessRuleViolation('restock_already_decided', __('returns.already_decided', ['sku' => $line->orderLine->sku]));
                }

                $line->update(['restock_decision' => $decision, 'decided_at' => now()]);
                if ($decision === RestockDecision::Restock) {
                    $changes[] = new StockChange($line->orderLine->variant_id, $return->order->warehouse_id, onHandDelta: $line->quantity);
                }
            }

            if ($changes !== []) {
                $this->stock->move(StockMovementType::ReturnRestock, $return, $changes);
            }
            $this->auditTrail->updated($return, $before);

            return $return;
        });
    }
}
