<?php

namespace App\Actions\Shipments;

use App\Actions\Orders\MoveOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;

/**
 * Moves an Order after its Shipments, inside the caller's transaction: Ready for shipping
 * becomes Shipped once every unit still ordered is in a Shipment, and Shipped becomes
 * Delivered once all its Shipments are delivered.
 */
final class FollowShipments
{
    public function __construct(private readonly MoveOrderStatus $moveOrderStatus) {}

    public function handle(Order $order): Order
    {
        if ($order->status === OrderStatus::ReadyForShipping && $order->load('lines')->isFullyShipped()) {
            [$order] = $this->moveOrderStatus->handle($order, OrderStatus::Shipped);
        }

        if ($order->status === OrderStatus::Shipped
            && $order->shipments()->get()->every(fn (Shipment $shipment): bool => $shipment->status === ShipmentStatus::Delivered)) {
            [$order] = $this->moveOrderStatus->handle($order, OrderStatus::Delivered);
        }

        return $order;
    }
}
