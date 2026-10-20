<?php

namespace App\Actions\Catalog;

use App\Enums\AuditAction;
use App\Models\Product;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Puts a Product's images in a new order; the first becomes the main image.
 */
final class ReorderProductImages
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  list<int>  $imageIds  every image of the Product, in the new order
     */
    public function handle(Product $product, array $imageIds): Product
    {
        DB::transaction(function () use ($product, $imageIds): void {
            $before = $product->imageAuditValues();

            foreach ($imageIds as $position => $id) {
                $product->images()->whereKey($id)->update(['position' => $position]);
            }

            $after = $product->imageAuditValues();
            if ($after !== $before) {
                $this->auditTrail->record(AuditAction::Updated, $product, $before, $after);
            }
        });

        return $product;
    }
}
