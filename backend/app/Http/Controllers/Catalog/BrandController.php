<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateBrand;
use App\Actions\Catalog\DeleteBrand;
use App\Actions\Catalog\ListBrands;
use App\Actions\Catalog\UpdateBrand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListBrandsRequest;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    public function index(ListBrandsRequest $request, ListBrands $listBrands): AnonymousResourceCollection
    {
        return BrandResource::collection($listBrands->handle($request->listParameters()));
    }

    public function store(StoreBrandRequest $request, CreateBrand $createBrand): JsonResponse
    {
        return (new BrandResource($createBrand->handle($request->values())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,brand`).
     */
    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand, UpdateBrand $updateBrand): BrandResource
    {
        return new BrandResource($updateBrand->handle($brand, $request->values()));
    }

    /**
     * Authorized by the route (`can:delete,brand`).
     */
    public function destroy(Brand $brand, DeleteBrand $deleteBrand): Response
    {
        $deleteBrand->handle($brand);

        return response()->noContent();
    }
}
