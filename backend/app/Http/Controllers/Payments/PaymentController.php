<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ListPayments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ListPaymentsRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Every Order's Payment ledger rows in one list. Roles with payments.view.
 */
class PaymentController extends Controller
{
    public function index(ListPaymentsRequest $request, ListPayments $listPayments): AnonymousResourceCollection
    {
        return PaymentResource::collection($listPayments->handle($request->listParameters()));
    }
}
