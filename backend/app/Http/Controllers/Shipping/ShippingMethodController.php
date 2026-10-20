<?php

namespace App\Http\Controllers\Shipping;

use App\Actions\Shipping\DeleteShippingMethod;
use App\Actions\Shipping\SaveShippingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\SaveShippingMethodRequest;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A Shipping zone's Shipping methods. They are listed with their zone (its `show`).
 */
class ShippingMethodController extends Controller
{
    public function store(SaveShippingMethodRequest $request, ShippingZone $shippingZone, SaveShippingMethod $saveShippingMethod): JsonResponse
    {
        return (new ShippingMethodResource($saveShippingMethod->handle($shippingZone, null, $request->values())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveShippingMethodRequest $request, ShippingMethod $shippingMethod, SaveShippingMethod $saveShippingMethod): ShippingMethodResource
    {
        return new ShippingMethodResource($saveShippingMethod->handle($shippingMethod->zone, $shippingMethod, $request->values()));
    }

    /**
     * Authorized by the route (`can:delete,shipping_method`).
     */
    public function destroy(ShippingMethod $shippingMethod, DeleteShippingMethod $deleteShippingMethod): Response
    {
        $deleteShippingMethod->handle($shippingMethod);

        return response()->noContent();
    }
}
