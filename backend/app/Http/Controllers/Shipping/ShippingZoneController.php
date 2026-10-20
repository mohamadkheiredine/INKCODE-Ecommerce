<?php

namespace App\Http\Controllers\Shipping;

use App\Actions\Shipping\DeleteShippingZone;
use App\Actions\Shipping\ListShippingZones;
use App\Actions\Shipping\SaveShippingZone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\ListShippingZonesRequest;
use App\Http\Requests\Shipping\StoreShippingZoneRequest;
use App\Http\Requests\Shipping\UpdateShippingZoneRequest;
use App\Http\Resources\ShippingZoneResource;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ShippingZoneController extends Controller
{
    public function index(ListShippingZonesRequest $request, ListShippingZones $listShippingZones): AnonymousResourceCollection
    {
        return ShippingZoneResource::collection($listShippingZones->handle($request->listParameters()));
    }

    public function store(StoreShippingZoneRequest $request, SaveShippingZone $saveShippingZone): JsonResponse
    {
        return (new ShippingZoneResource($saveShippingZone->handle(null, $request->values())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The zone with its places and all its Shipping methods, cheapest first.
     * Authorized by the route (`can:view,shipping_zone`).
     */
    public function show(ShippingZone $shippingZone): ShippingZoneResource
    {
        return new ShippingZoneResource($shippingZone->load([
            'locations',
            'methods' => fn ($methods) => $methods->orderBy('price')->orderBy('name'),
        ]));
    }

    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone, SaveShippingZone $saveShippingZone): ShippingZoneResource
    {
        return new ShippingZoneResource($saveShippingZone->handle($shippingZone, $request->values()));
    }

    /**
     * Authorized by the route (`can:delete,shipping_zone`).
     */
    public function destroy(ShippingZone $shippingZone, DeleteShippingZone $deleteShippingZone): Response
    {
        $deleteShippingZone->handle($shippingZone);

        return response()->noContent();
    }
}
