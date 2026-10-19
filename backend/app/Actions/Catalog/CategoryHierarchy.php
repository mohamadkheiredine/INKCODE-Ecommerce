<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * The two-level rule, checked inside the saving transaction with the rows locked, so a
 * parent cannot gain a parent while it receives a child (InnoDB's foreign-key check on a
 * new child waits for the parent's lock). Failures answer 422 on `parent_id`.
 */
final class CategoryHierarchy
{
    /**
     * @throws ValidationException when the parent is a child, is the Category itself, or
     *                             the Category has children and so must stay top-level
     */
    public static function assertAllowed(?Category $category, ?int $parentId): void
    {
        if ($category !== null) {
            Category::query()->lockForUpdate()->find($category->id);
        }
        if ($parentId === null) {
            return;
        }

        $parent = Category::query()->lockForUpdate()->findOrFail($parentId);

        $message = match (true) {
            $category !== null && $parent->id === $category->id => __('catalog.categories.own_parent'),
            ! $parent->isTopLevel() => __('catalog.categories.parent_is_child', ['name' => $parent->name]),
            $category !== null && $category->children()->exists() => __('catalog.categories.has_children_cannot_move'),
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['parent_id' => $message]);
        }
    }
}
