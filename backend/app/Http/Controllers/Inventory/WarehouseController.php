<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ListWarehouses;
use App\Actions\Inventory\SaveWarehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListWarehousesRequest;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Warehouses are never deleted, only deactivated: their stock history stays.
 */
class WarehouseController extends Controller
{
    public function index(ListWarehousesRequest $request, ListWarehouses $listWarehouses): AnonymousResourceCollection
    {
        return WarehouseResource::collection($listWarehouses->handle($request->listParameters()));
    }

    public function store(StoreWarehouseRequest $request, SaveWarehouse $saveWarehouse): JsonResponse
    {
        return (new WarehouseResource($saveWarehouse->handle(null, $request->values())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,warehouse`).
     */
    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse, SaveWarehouse $saveWarehouse): WarehouseResource
    {
        return new WarehouseResource($saveWarehouse->handle($warehouse, $request->values()));
    }
}
