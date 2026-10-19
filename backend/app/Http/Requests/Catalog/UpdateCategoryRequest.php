<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Validation\Validator;

/**
 * A Category's new values, all sent at once. An empty slug is generated again from the
 * name. A Category cannot become its own parent, and one with children stays top-level.
 */
class UpdateCategoryRequest extends StoreCategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->category();

        return $category !== null && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->category();
                if ($category === null || ! $this->filled('parent_id') || $validator->errors()->has('parent_id')) {
                    return;
                }

                if ($this->integer('parent_id') === $category->id) {
                    $validator->errors()->add('parent_id', __('catalog.categories.own_parent'));
                } elseif ($category->children()->exists()) {
                    $validator->errors()->add('parent_id', __('catalog.categories.has_children_cannot_move'));
                }
            },
            ...parent::after(),
        ];
    }
}
