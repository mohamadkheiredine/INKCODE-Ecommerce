<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ChangeTransferStatus;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;

class TransferStatusController extends Controller
{
    /**
     * Moves a Transfer one step: send (out of the source Warehouse, checked against
     * Available; then in transit), receive (into the destination Warehouse) or cancel
     * (a Draft only). The route gives the step as `status`; authorized by the route
     * (`can:update,transfer`) and safe to retry with an `Idempotency-Key` header.
     */
    public function __invoke(Transfer $transfer, string $status, ChangeTransferStatus $changeStatus): TransferResource
    {
        return new TransferResource($changeStatus->handle($transfer, TransferStatus::from($status))->loadForDisplay());
    }
}
