<?php

namespace App\Actions\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Category;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a Category nothing depends on. A parent with children is refused, and so is a
 * Category still referenced by other records (the Products of ticket #10, through
 * restricting foreign keys): both answer 409 with a clear message.
 */
final class DeleteCategory
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Category $category): void
    {
        DatabaseErrors::refuseDeleteInUse(
            fn () => DB::transaction(function () use ($category): void {
                $locked = Category::query()->lockForUpdate()->findOrFail($category->id);

                if ($locked->children()->exists()) {
                    throw new BusinessRuleViolation(
                        'category_has_children',
                        __('catalog.categories.has_children', ['name' => $locked->name]),
                    );
                }

                $locked->delete();
                $this->auditTrail->deleted($locked);
            }),
            __('catalog.categories.in_use', ['name' => $category->name]),
        );
    }
}
