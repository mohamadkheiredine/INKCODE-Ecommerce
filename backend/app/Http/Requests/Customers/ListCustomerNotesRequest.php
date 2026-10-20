<?php

namespace App\Http\Requests\Customers;

use App\Http\Requests\ListRequest;
use App\Models\Customer;

/**
 * A Customer's notes, newest first.
 */
class ListCustomerNotesRequest extends ListRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && ($this->user()?->can('view', $customer) ?? false);
    }

    protected function sorts(): array
    {
        return ['created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
