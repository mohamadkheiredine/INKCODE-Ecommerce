<?php

namespace App\Actions\Catalog;

use App\Enums\AuditAction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Adds images after a Product's existing ones. Files are stored under generated names
 * first; if saving fails, they are removed again, so no file is left without its row.
 */
final class AddProductImages
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(Product $product, array $files): Product
    {
        $paths = array_map(fn (UploadedFile $file): string => (string) $file->store('products', ProductImage::DISK), $files);

        try {
            DB::transaction(function () use ($product, $paths): void {
                Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $before = $product->imageAuditValues();
                $next = (int) $product->images()->max('position') + ($before['images'] === [] ? 0 : 1);

                foreach ($paths as $offset => $path) {
                    $product->images()->create(['path' => $path, 'position' => $next + $offset]);
                }

                $this->auditTrail->record(AuditAction::Updated, $product, $before, $product->imageAuditValues());
            });
        } catch (Throwable $exception) {
            Storage::disk(ProductImage::DISK)->delete($paths);

            throw $exception;
        }

        return $product;
    }
}
