<?php

namespace App\Actions\Orders;

use App\Enums\ProductStatus;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Up to ten Variants of Active Products whose SKU or Product name matches the search,
 * with their price and Available stock in every Warehouse together.
 */
final class FindSellableVariants
{
    private const int LIMIT = 10;

    /**
     * @return list<array{id: int, sku: string, options: list<array{attribute: string, value: string}>, price: string, product: array{id: int, name: string}, available: int}>
     */
    public function handle(string $search): array
    {
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return Variant::query()
            ->with(['product:id,name', 'attributeValues.attribute:id,name'])
            ->withSum('stockBalances as available', 'available')
            ->whereRelation('product', 'status', ProductStatus::Active)
            ->where(fn (Builder $query) => $query
                ->where('sku', 'like', $pattern)
                ->orWhereRelation('product', 'name', 'like', $pattern))
            ->orderBy('sku')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Variant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'options' => $variant->options(),
                'price' => $variant->price,
                'product' => $variant->product->only('id', 'name'),
                'available' => (int) $variant->getAttribute('available'),
            ])
            ->all();
    }
}
