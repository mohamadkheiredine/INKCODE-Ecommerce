<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ListRequest;
use App\Models\Category;

/**
 * The Categories list: search by name or slug, filter by level (`top` or `child`) or
 * by parent, sort by name, number of children or date.
 */
class ListCategoriesRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Category::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'children_count', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filters(): array
    {
        return [
            'level' => ['string', 'in:top,child'],
            'parent_id' => ['integer'],
        ];
    }
}
