<?php

namespace App\Actions\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A page of Orders with their Customer, Fulfilling warehouse and number of units. The
 * search matches the Order number or the Customer's name, email or phone.
 */
final class ListOrders
{
    /** @var array<string, string> */
    private const array COLUMN_FILTERS = ['status' => 'status', 'warehouse' => 'warehouse_id', 'customer' => 'customer_id'];

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['customer:id,name,email,phone', 'warehouse:id,name,code'])
            ->withSum('lines as units', 'quantity');

        if ($parameters->search !== null) {
            $query->where(function (Builder $query) use ($parameters): void {
                $parameters->search($query, ['number']);
                $query->orWhereIn('customer_id', $parameters->search(Customer::query()->select('id'), ['name', 'email', 'phone']));
            });
        }

        foreach (self::COLUMN_FILTERS as $filter => $column) {
            if ($parameters->hasFilter($filter)) {
                $query->where($column, $parameters->filter($filter));
            }
        }
        if ($parameters->hasFilter('from')) {
            $query->where('placed_at', '>=', Carbon::parse((string) $parameters->filter('from'))->startOfDay());
        }
        if ($parameters->hasFilter('to')) {
            $query->where('placed_at', '<=', Carbon::parse((string) $parameters->filter('to'))->endOfDay());
        }

        return $parameters->paginate($query);
    }
}
