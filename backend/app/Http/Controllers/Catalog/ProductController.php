<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\ListProducts;
use App\Actions\Catalog\UpdateProduct;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListProductsRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request, ListProducts $listProducts): AnonymousResourceCollection
    {
        return ProductResource::collection($listProducts->handle($request->listParameters()));
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        return (new ProductResource($createProduct->handle($request->values())->loadForDisplay()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,product`).
     */
    public function show(Request $request, Product $product): ProductResource
    {
        $product->loadForDisplay();

        // Each Variant's stock per Warehouse, for Roles that see stock.
        if ($request->user()?->can(Permission::InventoryView->value)) {
            $product->load('variants.stockBalances.warehouse:id,name,code');
        }

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): ProductResource
    {
        return new ProductResource($updateProduct->handle($product, $request->values())->loadForDisplay());
    }

    /**
     * Authorized by the route (`can:delete,product`).
     */
    public function destroy(Product $product, DeleteProduct $deleteProduct): Response
    {
        $deleteProduct->handle($product);

        return response()->noContent();
    }
}
