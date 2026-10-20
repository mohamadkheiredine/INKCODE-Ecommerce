<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ListStockMovements;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListStockMovementsRequest;
use App\Http\Resources\StockMovementResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function index(ListStockMovementsRequest $request, ListStockMovements $listStockMovements): AnonymousResourceCollection
    {
        return StockMovementResource::collection($listStockMovements->handle($request->listParameters()));
    }
}
