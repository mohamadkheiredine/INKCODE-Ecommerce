<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\ListOrders;
use App\Actions\Orders\UpdateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Orders are created Pending; their status changes only through the confirm, cancel and
 * advance endpoints (OrderConfirmationController, OrderCancellationController,
 * OrderAdvanceController, OrderLineCancellationController).
 */
class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, ListOrders $listOrders): AnonymousResourceCollection
    {
        return OrderResource::collection($listOrders->handle($request->listParameters()));
    }

    /**
     * Safe to retry with an `Idempotency-Key` header.
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        return (new OrderResource($createOrder->handle($request->values())->loadForDisplay()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,order`).
     */
    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->loadForDisplay());
    }

    /**
     * Changes a Pending or Confirmed Order's lines, addresses and Shipping method, adjusting
     * its reservations. A Locked order is refused with 409 `order_locked`. Safe to retry
     * with an `Idempotency-Key` header.
     */
    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $updateOrder): OrderResource
    {
        return new OrderResource($updateOrder->handle($order, $request->values())->loadForDisplay());
    }
}
