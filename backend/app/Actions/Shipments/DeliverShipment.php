<?php

namespace App\Actions\Shipments;

use App\Enums\AuditAction;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Marks a Shipment delivered, with its status history and an audit entry. Once the Order
 * is Shipped and all its Shipments are delivered, the Order becomes Delivered
 * ({@see FollowShipments}).
 */
final class DeliverShipment
{
    public function __construct(
        private readonly FollowShipments $followShipments,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_shipment_transition` when it is already delivered
     */
    public function handle(Order $order, Shipment $shipment, User $user): Order
    {
        return DB::transaction(function () use ($order, $shipment, $user): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $from = $shipment->status;

            if (! $from->canMoveTo(ShipmentStatus::Delivered)) {
                throw new BusinessRuleViolation('invalid_shipment_transition', __('shipments.invalid_transition', [
                    'number' => $shipment->number,
                    'from' => __("shipments.statuses.$from->value"),
                    'to' => __('shipments.statuses.delivered'),
                ]));
            }

            $shipment->update(['status' => ShipmentStatus::Delivered, 'delivered_at' => now()]);
            $shipment->statusChanges()->create(['from_status' => $from, 'to_status' => ShipmentStatus::Delivered, 'user_id' => $user->id]);
            $this->auditTrail->record(AuditAction::StatusChanged, $shipment, ['status' => $from->value], ['status' => ShipmentStatus::Delivered->value]);

            return $this->followShipments->handle($order);
        });
    }
}
