<?php

namespace App\Http\Requests\Catalog;

/**
 * A Category's new values, all sent at once. An empty slug is generated again from the
 * name. The hierarchy rules (two levels, not its own parent) live in CategoryHierarchy.
 */
class UpdateCategoryRequest extends StoreCategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->category();

        return $category !== null && ($this->user()?->can('update', $category) ?? false);
    }
}
