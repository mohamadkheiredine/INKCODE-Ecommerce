<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeleteVariant;
use App\Actions\Catalog\UpdateVariants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UpdateVariantsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\Response;

/**
 * A Product's Variants: edited together, removed one at a time. Both answer for the
 * Product as a whole, so the screen shows the saved state.
 */
class VariantController extends Controller
{
    public function update(UpdateVariantsRequest $request, Product $product, UpdateVariants $updateVariants): ProductResource
    {
        $updateVariants->handle($product, $request->variantValues());

        return new ProductResource($product->loadForDisplay());
    }

    /**
     * Authorized by the route (`can:update,product`); the Variant is scoped to the Product.
     */
    public function destroy(Product $product, Variant $variant, DeleteVariant $deleteVariant): Response
    {
        $deleteVariant->handle($variant);

        return response()->noContent();
    }
}
