<?php

namespace App\Http\Controllers\Returns;

use App\Actions\Returns\ListReturns;
use App\Actions\Returns\RequestReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\ListReturnsRequest;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Returns: the list and one Return (returns.view), and a new Return request on a
 * Delivered Order (returns.request).
 */
class ReturnController extends Controller
{
    public function index(ListReturnsRequest $request, ListReturns $listReturns): AnonymousResourceCollection
    {
        return ReturnResource::collection($listReturns->handle($request->listParameters()));
    }

    /**
     * Refused with 409 `order_not_delivered`, `return_window_closed` or
     * `return_exceeds_delivered` (more units than delivered minus already returned).
     */
    public function store(StoreReturnRequest $request, Order $order, RequestReturn $requestReturn): JsonResponse
    {
        $return = $requestReturn->handle($order, $request->returnRequest(), $request->user());

        return (new ReturnResource($return->loadForDisplay()))->response()->setStatusCode(201);
    }

    public function show(Request $request, OrderReturn $orderReturn): ReturnResource
    {
        return new ReturnResource($orderReturn->loadForDisplay());
    }
}
