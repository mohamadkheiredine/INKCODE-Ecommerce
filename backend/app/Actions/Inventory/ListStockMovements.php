<?php

namespace App\Actions\Inventory;

use App\Models\StockMovement;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of Stock movements with their Variant, Warehouse, User and source record.
 */
final class ListStockMovements
{
    /** Filters that match a column as given. */
    private const array EXACT_FILTERS = ['variant' => 'variant_id', 'warehouse' => 'warehouse_id', 'type' => 'type'];

    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = StockMovement::query()->with([
            'variant:id,product_id,sku',
            'variant.product:id,name',
            'variant.attributeValues.attribute:id,name',
            'warehouse:id,name,code',
            'user:id,name',
            'source',
        ]);

        if ($parameters->search !== null) {
            $query->whereHas('variant', fn (Builder $variants) => $parameters->search($variants, ['sku']));
        }

        foreach (self::EXACT_FILTERS as $filter => $column) {
            if ($parameters->hasFilter($filter)) {
                $query->where($column, $parameters->filter($filter));
            }
        }

        if ($parameters->hasFilter('from')) {
            $query->where('created_at', '>=', $parameters->filter('from').' 00:00:00');
        }

        if ($parameters->hasFilter('to')) {
            $query->where('created_at', '<=', $parameters->filter('to').' 23:59:59');
        }

        return $parameters->paginate($query);
    }
}
