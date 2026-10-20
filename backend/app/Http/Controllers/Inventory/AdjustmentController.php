<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateAdjustment;
use App\Actions\Inventory\ListAdjustments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListAdjustmentsRequest;
use App\Http\Requests\Inventory\StoreAdjustmentRequest;
use App\Http\Resources\AdjustmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Adjustments are recorded once and never edited or deleted.
 */
class AdjustmentController extends Controller
{
    public function index(ListAdjustmentsRequest $request, ListAdjustments $listAdjustments): AnonymousResourceCollection
    {
        return AdjustmentResource::collection($listAdjustments->handle($request->listParameters()));
    }

    /**
     * Safe to retry with an `Idempotency-Key` header.
     */
    public function store(StoreAdjustmentRequest $request, CreateAdjustment $createAdjustment): JsonResponse
    {
        $adjustment = $createAdjustment->handle($request->values())
            ->load(['warehouse:id,name,code', 'user:id,name', 'variant:id,product_id,sku', 'variant.product:id,name']);

        return (new AdjustmentResource($adjustment))->response()->setStatusCode(201);
    }
}
