<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ListReconciliation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ListReconciliationRequest;
use App\Http\Resources\ReconciliationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reconciliation of Order totals against ledger totals. Roles with payments.view.
 */
class ReconciliationController extends Controller
{
    /**
     * The Orders whose ledger total (payments minus Refunds) differs from what they owe.
     */
    public function index(ListReconciliationRequest $request, ListReconciliation $listReconciliation): AnonymousResourceCollection
    {
        return ReconciliationResource::collection($listReconciliation->handle($request->listParameters()));
    }
}
