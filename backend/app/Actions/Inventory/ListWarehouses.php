<?php

namespace App\Actions\Inventory;

use App\Models\Warehouse;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Warehouses, each with the units it has on hand.
 */
final class ListWarehouses
{
    /**
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Warehouse::query()->withSum('stockBalances as on_hand', 'on_hand');

        $parameters->search($query, ['name', 'code']);

        if ($parameters->hasFilter('status')) {
            $query->where('is_active', $parameters->filter('status') === 'active');
        }

        return $parameters->paginate($query);
    }
}
