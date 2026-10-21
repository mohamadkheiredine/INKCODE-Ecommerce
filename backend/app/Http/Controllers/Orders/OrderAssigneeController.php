<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\AssignOrder;
use App\Actions\Orders\ListOrderAssignees;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AssignOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderAssigneeController extends Controller
{
    /**
     * The Users an Order may be assigned to. Authorized by the route (orders.assign).
     */
    public function index(ListOrderAssignees $listOrderAssignees): JsonResponse
    {
        return response()->json(['data' => $listOrderAssignees->handle()]);
    }

    /**
     * Sets (or clears, with null) the Order's Assignee, who is notified in their bell.
     */
    public function update(AssignOrderRequest $request, Order $order, AssignOrder $assignOrder): OrderResource
    {
        return new OrderResource($assignOrder->handle($order, $request->assignee())->loadForDisplay());
    }
}
