<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateAttribute;
use App\Actions\Catalog\DeleteAttribute;
use App\Actions\Catalog\ListAttributes;
use App\Actions\Catalog\UpdateAttribute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListAttributesRequest;
use App\Http\Requests\Catalog\StoreAttributeRequest;
use App\Http\Requests\Catalog\UpdateAttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttributeController extends Controller
{
    public function index(ListAttributesRequest $request, ListAttributes $listAttributes): AnonymousResourceCollection
    {
        return AttributeResource::collection($listAttributes->handle($request->listParameters()));
    }

    public function store(StoreAttributeRequest $request, CreateAttribute $createAttribute): JsonResponse
    {
        return (new AttributeResource($createAttribute->handle($request->values())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,attribute`).
     */
    public function show(Attribute $attribute): AttributeResource
    {
        return new AttributeResource($attribute->load('values'));
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute, UpdateAttribute $updateAttribute): AttributeResource
    {
        return new AttributeResource($updateAttribute->handle($attribute, $request->values()));
    }

    /**
     * Authorized by the route (`can:delete,attribute`).
     */
    public function destroy(Attribute $attribute, DeleteAttribute $deleteAttribute): Response
    {
        $deleteAttribute->handle($attribute);

        return response()->noContent();
    }
}
