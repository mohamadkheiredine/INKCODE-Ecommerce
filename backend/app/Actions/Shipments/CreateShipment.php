<?php

namespace App\Actions\Shipments;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\OrderStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ships some units of a Ready for shipping Order (Partial fulfillment allows several
 * Shipments). In one transaction, under the Order's lock: checks each quantity against the
 * line's unshipped, uncancelled units, records the Shipment with its first status, deducts
 * the units not deducted yet ({@see OrderStock::ship()}), records an audit entry, and moves
 * the Order to Shipped once everything left has shipped ({@see FollowShipments}).
 */
final class CreateShipment
{
    public function __construct(
        private readonly OrderStock $orderStock,
        private readonly FollowShipments $followShipments,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array{carrier_id: int, tracking_number: string, lines: list<array{line_id: int, quantity: int}>}  $values  each line once
     *
     * @throws BusinessRuleViolation 409 `order_not_ready_for_shipping` before Ready for shipping or once Shipped
     * @throws ValidationException when a quantity is more than the line has left to ship
     */
    public function handle(Order $order, array $values, User $user): Order
    {
        return DB::transaction(function () use ($order, $values, $user): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== OrderStatus::ReadyForShipping) {
                throw new BusinessRuleViolation('order_not_ready_for_shipping', __('shipments.order_not_ready', [
                    'number' => $order->number,
                    'status' => __("orders.statuses.{$order->status->value}"),
                ]));
            }

            $lines = $order->lines->keyBy('id');
            foreach ($values['lines'] as $index => $shipped) {
                $line = $lines[$shipped['line_id']];
                $unshipped = $line->quantity - $line->shipped_quantity;
                if ($shipped['quantity'] > $unshipped) {
                    throw ValidationException::withMessages([
                        "lines.$index.quantity" => __('shipments.more_than_unshipped', ['sku' => $line->sku, 'quantity' => $unshipped]),
                    ]);
                }
            }

            $shipment = $order->shipments()->create([
                'number' => $order->number.'-'.($order->shipments()->count() + 1),
                'carrier_id' => $values['carrier_id'],
                'tracking_number' => $values['tracking_number'],
                'status' => ShipmentStatus::Shipped,
                'user_id' => $user->id,
                'shipped_at' => now(),
            ]);
            $shipment->lines()->createMany(array_map(
                fn (array $shipped): array => ['order_line_id' => $shipped['line_id'], 'quantity' => $shipped['quantity']],
                $values['lines'],
            ));
            $shipment->statusChanges()->create(['to_status' => ShipmentStatus::Shipped, 'user_id' => $user->id]);

            $this->orderStock->ship($order, array_column($values['lines'], 'quantity', 'line_id'));
            $this->auditTrail->created($shipment->load('lines'));

            return $this->followShipments->handle($order);
        });
    }
}
