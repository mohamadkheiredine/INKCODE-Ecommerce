<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ListStockLevels;
use App\Actions\Inventory\ListStockMovements;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListStockLevelsRequest;
use App\Http\Requests\Inventory\ListStockMovementsRequest;
use App\Http\Resources\StockLevelResource;
use App\Http\Resources\StockMovementResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    /**
     * Stock levels, with `meta.totals` (On-hand and Stock value across every page).
     */
    public function levels(ListStockLevelsRequest $request, ListStockLevels $listStockLevels): AnonymousResourceCollection
    {
        $parameters = $request->listParameters();

        return StockLevelResource::collection($listStockLevels->handle($parameters))
            ->additional(['meta' => ['totals' => $listStockLevels->totals($parameters)]]);
    }

    public function movements(ListStockMovementsRequest $request, ListStockMovements $listStockMovements): AnonymousResourceCollection
    {
        return StockMovementResource::collection($listStockMovements->handle($request->listParameters()));
    }
}
