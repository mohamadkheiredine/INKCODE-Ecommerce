<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of Customers with their Tags.
 */
final class ListCustomers
{
    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Customer::query()->with('tags');

        $parameters->search($query, ['name', 'email', 'phone']);

        if ($parameters->hasFilter('tag')) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey((int) $parameters->filter('tag')));
        }

        return $parameters->paginate($query);
    }
}
