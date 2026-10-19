<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\DeleteCategory;
use App\Actions\Catalog\ListCategories;
use App\Actions\Catalog\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListCategoriesRequest;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(ListCategoriesRequest $request, ListCategories $listCategories): AnonymousResourceCollection
    {
        return CategoryResource::collection($listCategories->handle($request->listParameters()));
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): JsonResponse
    {
        return (new CategoryResource($createCategory->handle($request->values())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,category`).
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('parent:id,name,slug')->loadCount('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): CategoryResource
    {
        return new CategoryResource($updateCategory->handle($category, $request->values()));
    }

    /**
     * Authorized by the route (`can:delete,category`).
     */
    public function destroy(Category $category, DeleteCategory $deleteCategory): Response
    {
        $deleteCategory->handle($category);

        return response()->noContent();
    }
}
