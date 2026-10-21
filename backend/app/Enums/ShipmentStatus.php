<?php

namespace App\Enums;

/**
 * Where a Shipment is: Shipped when it leaves the Fulfilling warehouse, then Delivered.
 * Separate from the Order status; the Order follows its Shipments (ticket 13).
 */
enum ShipmentStatus: string
{
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function canMoveTo(self $status): bool
    {
        return $this === self::Shipped && $status === self::Delivered;
    }
}
