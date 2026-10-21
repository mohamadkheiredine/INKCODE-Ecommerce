<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Services\OrderStock;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Moves a Confirmed Order one step forward (Preparing, then Ready for shipping). When the
 * new status is the store's Deduction stage, its Reserved units leave On-hand in the same
 * transaction. Confirming, cancelling and shipping have their own actions.
 */
final class AdvanceOrder
{
    public function __construct(
        private readonly MoveOrderStatus $moveOrderStatus,
        private readonly OrderStock $orderStock,
        private readonly Settings $settings,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition` for a skipped or backward move
     */
    public function handle(Order $order, OrderStatus $to): Order
    {
        return DB::transaction(function () use ($order, $to): Order {
            [$order] = $this->moveOrderStatus->handle($order, $to);

            if ($to->value === $this->settings->deductionStage()->value) {
                $this->orderStock->deduct($order);
            }

            return $order;
        });
    }
}
