<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\Variant;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Saves new SKUs, prices, Average costs and Low-stock thresholds for some of a
 * Product's Variants at once, recording each Variant that changed. Variants may swap
 * SKUs: changing SKUs are first parked on a value no real SKU can have ("#12"), so the
 * unique index never sees two at the same moment.
 */
final class UpdateVariants
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  list<array{id: int, sku: string, price: string, average_cost: string, low_stock_threshold: int|null}>  $rows
     */
    public function handle(Product $product, array $rows): void
    {
        DatabaseErrors::refuseDuplicate(fn () => DB::transaction(function () use ($product, $rows): void {
            $variants = $product->variants()->whereKey(array_column($rows, 'id'))->lockForUpdate()->get()->keyBy('id');

            $renamed = array_filter($rows, fn (array $row): bool => $variants[$row['id']]->sku !== $row['sku']);
            if ($renamed !== []) {
                Variant::query()->whereKey(array_column($renamed, 'id'))->update(['sku' => DB::raw("concat('#', id)")]);
            }

            foreach ($rows as $row) {
                /** @var Variant $variant */
                $variant = $variants[$row['id']];
                $before = $variant->auditValues();

                $variant->fill($row)->save();

                $this->auditTrail->updated($variant, $before);
            }
        }), 'variants', __('catalog.variants.try_again'));
    }
}
