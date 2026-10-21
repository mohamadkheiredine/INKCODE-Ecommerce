<?php

namespace App\Http\Controllers\Returns;

use App\Actions\Returns\DecideRestock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\RestockReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\OrderReturn;

/**
 * Restock decisions for lines of a Received Return (returns.receive). Refused with 409
 * `return_not_received` or `restock_already_decided`.
 */
class ReturnRestockController extends Controller
{
    public function store(RestockReturnRequest $request, OrderReturn $orderReturn, DecideRestock $decideRestock): ReturnResource
    {
        return new ReturnResource($decideRestock->handle($orderReturn, $request->decisions())->loadForDisplay());
    }
}
