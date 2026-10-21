<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A page of Customers with their Tags, Lifetime value, last Order date and Segment.
 */
final class ListCustomers
{
    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Customer::query()->withValue()->with('tags');

        $parameters->search($query, ['name', 'email', 'phone']);

        if ($parameters->hasFilter('tag')) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey((int) $parameters->filter('tag')));
        }
        if ($parameters->hasFilter('segment')) {
            $query->where('segment', $parameters->filter('segment'));
        }
        if ($parameters->hasFilter('lifetime_value_min')) {
            $query->where('lifetime_value', '>=', $parameters->filter('lifetime_value_min'));
        }
        if ($parameters->hasFilter('lifetime_value_max')) {
            $query->where('lifetime_value', '<=', $parameters->filter('lifetime_value_max'));
        }
        if ($parameters->hasFilter('last_order_from')) {
            $query->where('last_order_at', '>=', Carbon::parse((string) $parameters->filter('last_order_from'))->startOfDay());
        }
        if ($parameters->hasFilter('last_order_to')) {
            $query->where('last_order_at', '<=', Carbon::parse((string) $parameters->filter('last_order_to'))->endOfDay());
        }

        return $parameters->paginate($query);
    }
}
