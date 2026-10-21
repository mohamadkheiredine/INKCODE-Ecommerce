<?php

namespace App\Http\Controllers\Returns;

use App\Actions\Returns\MoveReturn;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnResource;
use App\Models\OrderReturn;

/**
 * Approved → Received, when the items arrive (returns.receive). Stock does not change.
 * An invalid step is refused with 409 `invalid_return_transition`.
 */
class ReturnReceiptController extends Controller
{
    public function store(OrderReturn $orderReturn, MoveReturn $moveReturn): ReturnResource
    {
        return new ReturnResource($moveReturn->handle($orderReturn, ReturnStatus::Received)->loadForDisplay());
    }
}
