<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Product, without Variants yet. Without a chosen slug, one is generated
 * from the name.
 */
final class CreateProduct
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array<string, mixed>&array{name: string, slug: string|null}  $values
     */
    public function handle(array $values): Product
    {
        return DatabaseErrors::refuseDuplicateSlug(fn (): Product => DB::transaction(function () use ($values): Product {
            $product = Product::query()->create([
                ...$values,
                'slug' => $values['slug'] ?? UniqueSlug::from($values['name'], Product::query(), 'product'),
            ]);

            $this->auditTrail->created($product);

            return $product;
        }));
    }
}
