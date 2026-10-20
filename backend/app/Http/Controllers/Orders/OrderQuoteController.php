<?php

namespace App\Http\Controllers\Orders;

use App\Actions\Orders\QuoteOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\QuoteOrderRequest;
use Illuminate\Http\JsonResponse;

class OrderQuoteController extends Controller
{
    /**
     * Prices lines before the Order exists (VAT per line, shipping, total) and lists each
     * active Warehouse with the lines it lacks stock for. Nothing is saved.
     */
    public function store(QuoteOrderRequest $request, QuoteOrder $quoteOrder): JsonResponse
    {
        return response()->json(['data' => $quoteOrder->handle($request->lines(), $request->shippingMethodId())]);
    }
}
