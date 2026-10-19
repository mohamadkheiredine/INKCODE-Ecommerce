<?php

namespace App\Http\Requests\Customers;

use App\Http\Requests\ListRequest;
use App\Models\Customer;

/**
 * The Tags on offer for the Customers filter and the Tag picker: search by name, sorted by name.
 */
class ListTagsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
