<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Category's new values. Without a slug, one is generated from the name (a
 * Category keeps its own slug when the name gives the same one). It cannot become its
 * own parent, go under a child, or leave the top level while it has children.
 */
final class UpdateCategory
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, slug: string|null, parent_id: int|null, description: string|null}  $values
     */
    public function handle(Category $category, array $values): Category
    {
        return DatabaseErrors::refuseDuplicateSlug(fn (): Category => DB::transaction(function () use ($category, $values): Category {
            CategoryHierarchy::assertAllowed($category, $values['parent_id']);
            $before = $category->auditValues();

            $category->update([
                ...$values,
                'slug' => $values['slug'] ?? UniqueSlug::from(
                    $values['name'],
                    Category::query()->whereKeyNot($category->id),
                    'category',
                ),
            ]);

            $this->auditTrail->updated($category, $before);

            return $category->loadForDisplay();
        }));
    }
}
