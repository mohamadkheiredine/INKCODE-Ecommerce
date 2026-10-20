<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\FindSellableVariants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\FindSellableVariantsRequest;
use Illuminate\Http\JsonResponse;

class SellableVariantController extends Controller
{
    /**
     * The Variants the Order form can add: Active Products only, matched by SKU or Product
     * name, with their price and Available stock across Warehouses.
     */
    public function index(FindSellableVariantsRequest $request, FindSellableVariants $findSellableVariants): JsonResponse
    {
        return response()->json(['data' => $findSellableVariants->handle($request->string('search')->trim()->toString())]);
    }
}
