<?php

namespace App\Http\Requests\Customers;

/**
 * A Customer's new details. Tags are replaced when `tags` is sent and kept otherwise.
 */
class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        $customer = $this->customer();

        return $customer !== null && ($this->user()?->can('update', $customer) ?? false);
    }
}
