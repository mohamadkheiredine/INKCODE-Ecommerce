<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Category under a top-level parent, or at the top. Without a chosen slug,
 * one is generated from the name.
 */
final class CreateCategory
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, slug: string|null, parent_id: int|null, description: string|null}  $values
     */
    public function handle(array $values): Category
    {
        return DatabaseErrors::refuseDuplicateSlug(fn (): Category => DB::transaction(function () use ($values): Category {
            CategoryHierarchy::assertAllowed(null, $values['parent_id']);

            $category = Category::query()->create([
                ...$values,
                'slug' => $values['slug'] ?? UniqueSlug::from($values['name'], Category::query(), 'category'),
            ]);

            $this->auditTrail->created($category);

            return $category->loadForDisplay();
        }));
    }
}
