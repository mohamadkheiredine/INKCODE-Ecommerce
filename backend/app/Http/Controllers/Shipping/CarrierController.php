<?php

namespace App\Http\Controllers\Shipping;

use App\Actions\Shipping\DeleteCarrier;
use App\Actions\Shipping\ListCarriers;
use App\Actions\Shipping\SaveCarrier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\ListCarriersRequest;
use App\Http\Requests\Shipping\StoreCarrierRequest;
use App\Http\Requests\Shipping\UpdateCarrierRequest;
use App\Http\Resources\CarrierResource;
use App\Models\Carrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CarrierController extends Controller
{
    public function index(ListCarriersRequest $request, ListCarriers $listCarriers): AnonymousResourceCollection
    {
        return CarrierResource::collection($listCarriers->handle($request->listParameters()));
    }

    public function store(StoreCarrierRequest $request, SaveCarrier $saveCarrier): JsonResponse
    {
        return (new CarrierResource($saveCarrier->handle(null, $request->values())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,carrier`).
     */
    public function show(Carrier $carrier): CarrierResource
    {
        return new CarrierResource($carrier);
    }

    public function update(UpdateCarrierRequest $request, Carrier $carrier, SaveCarrier $saveCarrier): CarrierResource
    {
        return new CarrierResource($saveCarrier->handle($carrier, $request->values()));
    }

    /**
     * Authorized by the route (`can:delete,carrier`).
     */
    public function destroy(Carrier $carrier, DeleteCarrier $deleteCarrier): Response
    {
        $deleteCarrier->handle($carrier);

        return response()->noContent();
    }
}
