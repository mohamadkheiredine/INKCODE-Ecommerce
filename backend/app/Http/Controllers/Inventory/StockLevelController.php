<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ListStockLevels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListStockLevelsRequest;
use App\Http\Resources\StockLevelResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockLevelController extends Controller
{
    /**
     * Stock levels, with `meta.totals` (On-hand and Stock value across every page).
     */
    public function index(ListStockLevelsRequest $request, ListStockLevels $listStockLevels): AnonymousResourceCollection
    {
        $parameters = $request->listParameters();

        return StockLevelResource::collection($listStockLevels->handle($parameters))
            ->additional(['meta' => ['totals' => $listStockLevels->totals($parameters)]]);
    }
}
