<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A page of Payment ledger rows across Orders, each with its Order and the User who
 * recorded it. The search matches the Order number or the gateway reference.
 */
final class ListPayments
{
    /** @var list<string> */
    private const array COLUMN_FILTERS = ['kind', 'method', 'status'];

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Payment::query()->with(['order:id,number,currency_code,payment_status', 'user:id,name', 'orderReturn:id,number,refund_id']);

        if ($parameters->search !== null) {
            $query->where(function (Builder $query) use ($parameters): void {
                $parameters->search($query, ['gateway_reference']);
                $query->orWhereIn('order_id', $parameters->search(Order::query()->select('id'), ['number']));
            });
        }

        foreach (self::COLUMN_FILTERS as $filter) {
            if ($parameters->hasFilter($filter)) {
                $query->where($filter, $parameters->filter($filter));
            }
        }
        if ($parameters->hasFilter('from')) {
            $query->where('created_at', '>=', Carbon::parse((string) $parameters->filter('from'))->startOfDay());
        }
        if ($parameters->hasFilter('to')) {
            $query->where('created_at', '<=', Carbon::parse((string) $parameters->filter('to'))->endOfDay());
        }

        return $parameters->paginate($query);
    }
}
