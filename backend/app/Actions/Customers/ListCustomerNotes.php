<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of a Customer's notes with their authors.
 */
final class ListCustomerNotes
{
    /**
     * @return LengthAwarePaginator<int, CustomerNote>
     */
    public function handle(Customer $customer, ListParameters $parameters): LengthAwarePaginator
    {
        return $parameters->paginate($customer->notes()->getQuery()->with('author'));
    }
}
