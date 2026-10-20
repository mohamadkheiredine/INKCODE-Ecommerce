<?php

namespace App\Actions\Inventory;

use App\Models\Adjustment;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAdjustments
{
    /**
     * @return LengthAwarePaginator<int, Adjustment>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Adjustment::query()->with([
            'warehouse:id,name,code',
            'user:id,name',
            'variant:id,product_id,sku',
            'variant.product:id,name',
        ]);

        $parameters->search($query, ['number']);

        foreach (['warehouse' => 'warehouse_id', 'reason' => 'reason'] as $filter => $column) {
            if ($parameters->hasFilter($filter)) {
                $query->where($column, $parameters->filter($filter));
            }
        }

        return $parameters->paginate($query);
    }
}
