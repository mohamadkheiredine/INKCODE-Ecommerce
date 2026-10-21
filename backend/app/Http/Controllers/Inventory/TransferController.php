<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateTransfer;
use App\Actions\Inventory\ListTransfers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListTransfersRequest;
use App\Http\Requests\Inventory\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Transfers are created as Drafts, then sent, received or cancelled through
 * TransferStatusController; status changes are authorized by the route
 * (`can:update,transfer`) and safe to retry.
 */
class TransferController extends Controller
{
    public function index(ListTransfersRequest $request, ListTransfers $listTransfers): AnonymousResourceCollection
    {
        return TransferResource::collection($listTransfers->handle($request->listParameters()));
    }

    public function store(StoreTransferRequest $request, CreateTransfer $createTransfer): JsonResponse
    {
        return (new TransferResource($createTransfer->handle($request->values())->loadForDisplay()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,transfer`).
     */
    public function show(Transfer $transfer): TransferResource
    {
        return new TransferResource($transfer->loadForDisplay());
    }
}
