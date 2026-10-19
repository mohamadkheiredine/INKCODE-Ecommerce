<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\AddProductImages;
use App\Actions\Catalog\RemoveProductImage;
use App\Actions\Catalog\ReorderProductImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ReorderProductImagesRequest;
use App\Http\Requests\Catalog\StoreProductImagesRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;

/**
 * A Product's images, uploaded on their own (multipart) so the Product stays plain
 * JSON. Every answer is the whole Product, so the screen shows the saved order.
 */
class ProductImageController extends Controller
{
    public function store(StoreProductImagesRequest $request, Product $product, AddProductImages $addProductImages): ProductResource
    {
        return new ProductResource($addProductImages->handle($product, $request->images())->loadForDisplay());
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product, ReorderProductImages $reorderProductImages): ProductResource
    {
        return new ProductResource($reorderProductImages->handle($product, $request->imageIds())->loadForDisplay());
    }

    /**
     * Authorized by the route (`can:update,product`); the image is scoped to the Product.
     */
    public function destroy(Product $product, ProductImage $image, RemoveProductImage $removeProductImage): ProductResource
    {
        return new ProductResource($removeProductImage->handle($product, $image)->loadForDisplay());
    }
}
