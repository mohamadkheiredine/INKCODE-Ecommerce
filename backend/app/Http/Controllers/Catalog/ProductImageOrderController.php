<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ReorderProductImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ReorderProductImagesRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;

/**
 * The order of a Product's images; the first is the main image.
 */
class ProductImageOrderController extends Controller
{
    public function update(ReorderProductImagesRequest $request, Product $product, ReorderProductImages $reorderProductImages): ProductResource
    {
        return new ProductResource($reorderProductImages->handle($product, $request->imageIds())->loadForDisplay());
    }
}
