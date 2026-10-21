<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\ListCustomers;
use App\Actions\Customers\UpdateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\ListCustomersRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function index(ListCustomersRequest $request, ListCustomers $listCustomers): AnonymousResourceCollection
    {
        return CustomerResource::collection($listCustomers->handle($request->listParameters()));
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        $customer = $createCustomer->handle($request->details(), $request->tagNames() ?? []);

        return $this->profile($customer)->response()->setStatusCode(201);
    }

    /**
     * Authorized by the route (`can:view,customer`).
     */
    public function show(Customer $customer): CustomerResource
    {
        return $this->profile($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): CustomerResource
    {
        return $this->profile($updateCustomer->handle($customer, $request->details(), $request->tagNames()));
    }

    /**
     * Authorized by the route (`can:delete,customer`).
     */
    public function destroy(Customer $customer, DeleteCustomer $deleteCustomer): Response
    {
        $deleteCustomer->handle($customer);

        return response()->noContent();
    }

    private function profile(Customer $customer): CustomerResource
    {
        return new CustomerResource(Customer::query()->withValue()->with(['tags', 'addresses'])->findOrFail($customer->id));
    }
}
