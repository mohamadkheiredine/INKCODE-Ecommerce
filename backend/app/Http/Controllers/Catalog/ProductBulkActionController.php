<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ApplyProductBulkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductBulkActionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Answers how many Products changed or were deleted, and which were skipped and why.
 */
class ProductBulkActionController extends Controller
{
    public function store(ProductBulkActionRequest $request, ApplyProductBulkAction $applyProductBulkAction): JsonResponse
    {
        return response()->json(['data' => $applyProductBulkAction->handle($request->action(), $request->productIds())]);
    }
}
