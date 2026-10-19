<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a Product, its Variants and its image files. A Product that appears on any
 * Order (whose lines restrict deleting their Variants, #16) is refused with 409
 * `in_use`: it can only be Archived.
 */
final class DeleteProduct
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Product $product): void
    {
        $paths = $product->images()->pluck('path')->all();

        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($product): void {
                $product->delete();
                $this->auditTrail->deleted($product);
            }),
            __('catalog.products.in_use', ['name' => $product->name]),
        );

        Storage::disk(ProductImage::DISK)->delete($paths);
    }
}
