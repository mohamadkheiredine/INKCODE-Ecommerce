<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\GenerateVariants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\GenerateVariantsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;

/**
 * Generates a Product's missing Variants and answers with the whole Product, plus
 * `meta.generated`: how many Variants are new.
 */
class VariantGenerationController extends Controller
{
    public function store(GenerateVariantsRequest $request, Product $product, GenerateVariants $generateVariants): ProductResource
    {
        $generated = $generateVariants->handle($product, $request->valueIds());

        return (new ProductResource($product->loadForDisplay()))->additional(['meta' => ['generated' => $generated]]);
    }
}
