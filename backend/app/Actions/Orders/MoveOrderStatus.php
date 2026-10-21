<?php

namespace App\Actions\Orders;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Services\AuditTrail;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

/**
 * The only way an Order's status changes. Inside the caller's transaction it locks the
 * Order's row (so two requests never move the same Order at once), refuses a move the
 * flow does not allow ({@see OrderStatus::canMoveTo()}) with 409 `invalid_transition`,
 * then saves the new status, appends it to the status history and records an audit entry.
 * Callers do their side effects (reserving, releasing) in the same transaction.
 */
final class MoveOrderStatus
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @return array{Order, OrderStatus} the locked, updated Order and the status it left
     *
     * @throws BusinessRuleViolation 409 `invalid_transition`
     */
    public function handle(Order $order, OrderStatus $to, ?string $note = null): array
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Move an Order inside a transaction, together with its side effects.');
        }

        $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $from = $order->status;

        if (! $from->canMoveTo($to)) {
            throw self::refusal($order, $to);
        }

        $userId = $this->auth->guard()->id();
        $order->update(['status' => $to]);
        $order->statusChanges()->create([
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId === null ? null : (int) $userId,
            'note' => $note,
        ]);
        $this->auditTrail->record(AuditAction::StatusChanged, $order, ['status' => $from->value], ['status' => $to->value]);

        return [$order, $from];
    }

    /**
     * 409 `invalid_transition`: the Order cannot become `$to` from where it is. `details.allowed`
     * lists the statuses it may move to instead.
     */
    public static function refusal(Order $order, OrderStatus $to): BusinessRuleViolation
    {
        return new BusinessRuleViolation('invalid_transition', __('orders.invalid_transition', [
            'number' => $order->number,
            'from' => __("orders.statuses.{$order->status->value}"),
            'to' => __("orders.statuses.$to->value"),
        ]), details: ['allowed' => array_map(fn (OrderStatus $status): string => $status->value, $order->status->allowedMoves())]);
    }
}
