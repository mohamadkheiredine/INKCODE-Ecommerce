<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\AddCustomerNote;
use App\Actions\Customers\ListCustomerNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\ListCustomerNotesRequest;
use App\Http\Requests\Customers\StoreCustomerNoteRequest;
use App\Http\Resources\CustomerNoteResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Internal notes on a Customer. Notes are only added, so there is no update or delete.
 */
class CustomerNoteController extends Controller
{
    public function index(ListCustomerNotesRequest $request, Customer $customer, ListCustomerNotes $listCustomerNotes): AnonymousResourceCollection
    {
        return CustomerNoteResource::collection($listCustomerNotes->handle($customer, $request->listParameters()));
    }

    public function store(StoreCustomerNoteRequest $request, Customer $customer, AddCustomerNote $addCustomerNote): JsonResponse
    {
        /** @var User $author */
        $author = $request->user();

        return (new CustomerNoteResource($addCustomerNote->handle($customer, $author, $request->body())))
            ->response()
            ->setStatusCode(201);
    }
}
