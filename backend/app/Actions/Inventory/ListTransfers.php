<?php

namespace App\Actions\Inventory;

use App\Models\Transfer;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListTransfers
{
    /**
     * @return LengthAwarePaginator<int, Transfer>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Transfer::query()
            ->with(['sourceWarehouse:id,name,code', 'destinationWarehouse:id,name,code', 'user:id,name'])
            ->withSum('lines as units', 'quantity');

        $parameters->search($query, ['number']);

        if ($parameters->hasFilter('status')) {
            $query->where('status', $parameters->filter('status'));
        }

        if ($parameters->hasFilter('warehouse')) {
            $warehouse = $parameters->filter('warehouse');
            $query->where(fn (Builder $inner) => $inner
                ->where('source_warehouse_id', $warehouse)
                ->orWhere('destination_warehouse_id', $warehouse));
        }

        return $parameters->paginate($query);
    }
}
