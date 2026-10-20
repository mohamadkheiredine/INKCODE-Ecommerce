<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateReceipt;
use App\Actions\Inventory\ListReceipts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListReceiptsRequest;
use App\Http\Requests\Inventory\StoreReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Receipts are recorded once and never edited or deleted; Adjustments correct mistakes.
 */
class ReceiptController extends Controller
{
    public function index(ListReceiptsRequest $request, ListReceipts $listReceipts): AnonymousResourceCollection
    {
        return ReceiptResource::collection($listReceipts->handle($request->listParameters()));
    }

    /**
     * Safe to retry with an `Idempotency-Key` header.
     */
    public function store(StoreReceiptRequest $request, CreateReceipt $createReceipt): JsonResponse
    {
        return (new ReceiptResource($this->loadForDisplay($createReceipt->handle($request->values()))))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,receipt`).
     */
    public function show(Receipt $receipt): ReceiptResource
    {
        return new ReceiptResource($this->loadForDisplay($receipt));
    }

    private function loadForDisplay(Receipt $receipt): Receipt
    {
        return $receipt->load([
            'warehouse:id,name,code',
            'user:id,name',
            'lines.variant:id,product_id,sku',
            'lines.variant.product:id,name',
            'lines.variant.attributeValues.attribute:id,name',
        ]);
    }
}
