<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\AddCustomerAddress;
use App\Actions\Customers\RemoveCustomerAddress;
use App\Actions\Customers\UpdateCustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A Customer's addresses. The route binding is scoped, so an address of another Customer is a 404.
 */
class CustomerAddressController extends Controller
{
    public function store(StoreCustomerAddressRequest $request, Customer $customer, AddCustomerAddress $addCustomerAddress): JsonResponse
    {
        $address = $addCustomerAddress->handle($customer, $request->fields(), $request->defaults());

        return (new CustomerAddressResource($address))->response()->setStatusCode(201);
    }

    public function update(StoreCustomerAddressRequest $request, Customer $customer, CustomerAddress $address, UpdateCustomerAddress $updateCustomerAddress): CustomerAddressResource
    {
        return new CustomerAddressResource($updateCustomerAddress->handle($address, $request->fields(), $request->defaults()));
    }

    /**
     * Authorized by the route (`can:update,customer`).
     */
    public function destroy(Customer $customer, CustomerAddress $address, RemoveCustomerAddress $removeCustomerAddress): Response
    {
        $removeCustomerAddress->handle($address);

        return response()->noContent();
    }
}
