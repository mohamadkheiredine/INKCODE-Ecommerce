<?php

namespace App\Http\Resources;

use App\Actions\Inventory\ListStockLevels;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Variant in one Warehouse, as selected by {@see ListStockLevels}. Amounts are
 * decimal strings; `low_stock_threshold` is the one that applies (the Variant's own or
 * the store default).
 *
 * @mixin Variant
 */
class StockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $available = (int) $this->getAttribute('available');
        $threshold = (int) $this->getAttribute('effective_threshold');

        return [
            'variant' => [
                'id' => $this->id,
                'sku' => $this->sku,
                'options' => $this->options(),
            ],
            'product' => ['id' => $this->product_id, 'name' => $this->getAttribute('product_name')],
            'warehouse' => [
                'id' => (int) $this->getAttribute('warehouse_id'),
                'name' => $this->getAttribute('warehouse_name'),
                'code' => $this->getAttribute('warehouse_code'),
            ],
            'on_hand' => (int) $this->getAttribute('on_hand'),
            'reserved' => (int) $this->getAttribute('reserved'),
            'available' => $available,
            'average_cost' => $this->average_cost,
            'stock_value' => (string) $this->getAttribute('stock_value'),
            'low_stock_threshold' => $threshold,
            'is_low_stock' => $available <= $threshold,
        ];
    }
}
