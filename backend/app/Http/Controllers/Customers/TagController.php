<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\ListTags;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\ListTagsRequest;
use App\Http\Resources\TagResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The Tags Users have put on Customers. Tags are made by tagging a Customer, so this only lists them.
 */
class TagController extends Controller
{
    public function index(ListTagsRequest $request, ListTags $listTags): AnonymousResourceCollection
    {
        return TagResource::collection($listTags->handle($request->listParameters()));
    }
}
