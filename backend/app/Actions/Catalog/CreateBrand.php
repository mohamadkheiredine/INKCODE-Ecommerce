<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Services\AuditTrail;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Brand. Without a chosen slug, one is generated from the name.
 */
final class CreateBrand
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, slug: string|null}  $values
     */
    public function handle(array $values): Brand
    {
        return DB::transaction(function () use ($values): Brand {
            $brand = Brand::query()->create([
                ...$values,
                'slug' => $values['slug'] ?? UniqueSlug::from($values['name'], Brand::query(), 'brand'),
            ]);

            $this->auditTrail->created($brand);

            return $brand;
        });
    }
}
