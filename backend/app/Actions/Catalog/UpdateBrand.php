<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Brand's new name and slug. Without a slug, one is generated from the name.
 */
final class UpdateBrand
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, slug: string|null}  $values
     */
    public function handle(Brand $brand, array $values): Brand
    {
        return DatabaseErrors::refuseDuplicateSlug(fn (): Brand => DB::transaction(function () use ($brand, $values): Brand {
            $before = $brand->auditValues();

            $brand->update([
                ...$values,
                'slug' => $values['slug'] ?? UniqueSlug::from($values['name'], Brand::query()->whereKeyNot($brand->id), 'brand'),
            ]);

            $this->auditTrail->updated($brand, $before);

            return $brand;
        }));
    }
}
