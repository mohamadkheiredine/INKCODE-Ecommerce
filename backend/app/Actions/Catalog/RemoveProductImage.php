<?php

namespace App\Actions\Catalog;

use App\Enums\AuditAction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes one image of a Product, then its file once the change is saved. The next
 * image in line becomes the main image when the main one is removed.
 */
final class RemoveProductImage
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Product $product, ProductImage $image): Product
    {
        DB::transaction(function () use ($product, $image): void {
            $before = $product->imageAuditValues();
            $image->delete();
            $this->auditTrail->record(AuditAction::Updated, $product, $before, $product->imageAuditValues());
        });

        Storage::disk(ProductImage::DISK)->delete($image->path);

        return $product;
    }
}
