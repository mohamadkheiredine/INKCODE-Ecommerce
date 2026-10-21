<?php

namespace App\Http\Controllers\Shipments;

use App\Actions\Shipments\CreateShipment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;

class ShipmentController extends Controller
{
    /**
     * Ships some units of a Ready for shipping Order. The Order becomes Shipped once
     * everything left has shipped. Refused with 409 `order_not_ready_for_shipping` in any
     * other status; a quantity above what the line has left to ship is a 422. Safe to retry
     * with an `Idempotency-Key` header.
     */
    public function store(StoreShipmentRequest $request, Order $order, CreateShipment $createShipment): OrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new OrderResource($createShipment->handle($order, $request->shipment(), $user)->loadForDisplay());
    }
}
