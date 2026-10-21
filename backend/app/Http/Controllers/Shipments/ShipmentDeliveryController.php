<?php

namespace App\Http\Controllers\Shipments;

use App\Actions\Shipments\DeliverShipment;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;

class ShipmentDeliveryController extends Controller
{
    /**
     * Marks the Shipment delivered. The Order becomes Delivered once it is Shipped and all
     * its Shipments are delivered. A delivered Shipment is refused with 409
     * `invalid_shipment_transition`. Safe to retry with an `Idempotency-Key` header.
     */
    public function store(Request $request, Order $order, Shipment $shipment, DeliverShipment $deliverShipment): OrderResource
    {
        /** @var User $user */
        $user = $request->user();

        return new OrderResource($deliverShipment->handle($order, $shipment, $user)->loadForDisplay());
    }
}
