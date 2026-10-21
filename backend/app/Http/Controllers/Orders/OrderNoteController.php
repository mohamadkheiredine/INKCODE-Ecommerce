<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\AddOrderNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderNoteRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OrderNoteController extends Controller
{
    /**
     * Adds a note to the Order's timeline (Locked orders too) and answers with the Order.
     */
    public function store(StoreOrderNoteRequest $request, Order $order, AddOrderNote $addOrderNote): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $addOrderNote->handle($order, $user, $request->body());

        return (new OrderResource($order->loadForDisplay()))->response()->setStatusCode(201);
    }
}
