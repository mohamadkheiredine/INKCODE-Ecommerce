<?php

namespace App\Http\Controllers\Returns;

use App\Actions\Returns\MoveReturn;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\DecideReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\OrderReturn;

/**
 * Approves or rejects a Requested Return (returns.approve); the route sets `status`. A
 * Rejected Return frees its units to be requested again. An invalid step is refused with
 * 409 `invalid_return_transition`.
 */
class ReturnDecisionController extends Controller
{
    public function store(DecideReturnRequest $request, OrderReturn $orderReturn, MoveReturn $moveReturn): ReturnResource
    {
        $status = ReturnStatus::from((string) $request->route('status'));

        return new ReturnResource($moveReturn->handle($orderReturn, $status, $request->note())->loadForDisplay());
    }
}
