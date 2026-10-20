<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\ListOrders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Orders are created Pending; their status changes only through the confirm and cancel
 * endpoints (OrderConfirmationController, OrderCancellationController).
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
        return (new OrderResource(self::loadForDisplay($createOrder->handle($request->values()))))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,order`).
     */
    public function show(Order $order): OrderResource
    {
        return new OrderResource(self::loadForDisplay($order));
    }

    public static function loadForDisplay(Order $order): Order
    {
        return $order->load([
            'customer:id,name,email,phone',
            'warehouse:id,name,code',
            'user:id,name',
            'lines.variant:id,product_id',
            'statusChanges.user:id,name',
        ]);
    }
}
