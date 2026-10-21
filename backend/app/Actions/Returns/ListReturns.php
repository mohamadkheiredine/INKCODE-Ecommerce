<?php

namespace App\Actions\Returns;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of Returns, each with its Order, Customer and units. The search matches the
 * Return number, the Order number or the Customer's name.
 */
final class ListReturns
{
    /** @var list<string> */
    private const array COLUMN_FILTERS = ['status', 'reason'];

    /**
     * @return LengthAwarePaginator<int, OrderReturn>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = OrderReturn::query()
            ->with(['order:id,number,currency_code,customer_id', 'order.customer:id,name'])
            ->withSum('lines as units', 'quantity');

        if ($parameters->search !== null) {
            $query->where(function (Builder $query) use ($parameters): void {
                $parameters->search($query, ['number']);
                $query->orWhereIn('order_id', $parameters->search(Order::query()->select('id'), ['number']))
                    ->orWhereIn('order_id', Order::query()->select('id')->whereIn('customer_id', $parameters->search(Customer::query()->select('id'), ['name'])));
            });
        }

        foreach (self::COLUMN_FILTERS as $filter) {
            if ($parameters->hasFilter($filter)) {
                $query->where($filter, $parameters->filter($filter));
            }
        }

        return $parameters->paginate($query);
    }
}
