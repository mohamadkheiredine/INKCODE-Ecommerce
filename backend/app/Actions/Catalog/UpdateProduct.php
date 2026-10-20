<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Product's details. Its Variants keep their own prices: changing the base
 * price only pre-fills Variants generated later. Orders keep their own snapshot (#16).
 */
final class UpdateProduct
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array<string, mixed>&array{name: string, slug: string|null}  $values
     */
    public function handle(Product $product, array $values): Product
    {
        return DatabaseErrors::refuseDuplicateSlug(fn (): Product => DB::transaction(function () use ($product, $values): Product {
            $before = $product->auditValues();

            $product->update([
                ...$values,
                'slug' => $values['slug'] ?? UniqueSlug::from($values['name'], Product::query()->whereKeyNot($product->id), 'product'),
            ]);

            $this->auditTrail->updated($product, $before);

            return $product;
        }));
    }
}
