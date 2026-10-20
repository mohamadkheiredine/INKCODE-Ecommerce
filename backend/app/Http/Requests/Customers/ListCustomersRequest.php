<?php

namespace App\Http\Requests\Customers;

use App\Http\Requests\ListRequest;
use App\Models\Customer;

/**
 * The Customers list: search by name, email or phone, filter by a Tag's id, sort by name
 * or date (newest first by default).
 */
class ListCustomersRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filters(): array
    {
        return ['tag' => ['integer']];
    }
}
