<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ChangeTransferStatus;
use App\Actions\Inventory\CreateTransfer;
use App\Actions\Inventory\ListTransfers;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListTransfersRequest;
use App\Http\Requests\Inventory\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Transfers are created as Drafts, then sent, received or cancelled; status changes are
 * authorized by the route (`can:update,transfer`) and safe to retry.
 */
class TransferController extends Controller
{
    public function index(ListTransfersRequest $request, ListTransfers $listTransfers): AnonymousResourceCollection
    {
        return TransferResource::collection($listTransfers->handle($request->listParameters()));
    }

    public function store(StoreTransferRequest $request, CreateTransfer $createTransfer): JsonResponse
    {
        return (new TransferResource($this->loadForDisplay($createTransfer->handle($request->values()))))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,transfer`).
     */
    public function show(Transfer $transfer): TransferResource
    {
        return new TransferResource($this->loadForDisplay($transfer));
    }

    /**
     * Takes the lines out of the source Warehouse (checked against Available); they are then in transit.
     */
    public function send(Transfer $transfer, ChangeTransferStatus $changeStatus): TransferResource
    {
        return new TransferResource($this->loadForDisplay($changeStatus->handle($transfer, TransferStatus::InTransit)));
    }

    /**
     * Puts the in-transit lines into the destination Warehouse.
     */
    public function receive(Transfer $transfer, ChangeTransferStatus $changeStatus): TransferResource
    {
        return new TransferResource($this->loadForDisplay($changeStatus->handle($transfer, TransferStatus::Received)));
    }

    /**
     * Only a Draft (not yet sent) can be cancelled.
     */
    public function cancel(Transfer $transfer, ChangeTransferStatus $changeStatus): TransferResource
    {
        return new TransferResource($this->loadForDisplay($changeStatus->handle($transfer, TransferStatus::Cancelled)));
    }

    private function loadForDisplay(Transfer $transfer): Transfer
    {
        return $transfer->load([
            'sourceWarehouse:id,name,code',
            'destinationWarehouse:id,name,code',
            'user:id,name',
            'lines.variant:id,product_id,sku',
            'lines.variant.product:id,name',
        ]);
    }
}
