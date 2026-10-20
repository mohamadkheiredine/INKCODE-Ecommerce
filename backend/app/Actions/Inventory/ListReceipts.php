<?php

namespace App\Actions\Inventory;

use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Receipts with their Warehouse, the User who recorded them, the units received
 * and their total cost.
 */
final class ListReceipts
{
    /**
     * @return LengthAwarePaginator<int, Receipt>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Receipt::query()
            ->with(['warehouse:id,name,code', 'user:id,name'])
            ->withSum('lines as units', 'quantity')
            ->addSelect(['total_cost' => ReceiptLine::query()
                ->selectRaw('CAST(SUM(quantity * unit_cost) AS DECIMAL(20, 2))')
                ->whereColumn('receipt_id', 'receipts.id')]);

        $parameters->search($query, ['number', 'supplier_reference']);

        if ($parameters->hasFilter('warehouse')) {
            $query->where('warehouse_id', $parameters->filter('warehouse'));
        }

        return $parameters->paginate($query);
    }
}
