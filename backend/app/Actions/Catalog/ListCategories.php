<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Categories, each with its parent and how many children it has.
 */
final class ListCategories
{
    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Category::query()->with('parent:id,name,slug')->withCount('children');

        $parameters->search($query, ['name', 'slug']);

        if ($parameters->hasFilter('level')) {
            $parameters->filter('level') === 'top'
                ? $query->whereNull('parent_id')
                : $query->whereNotNull('parent_id');
        }

        if ($parameters->hasFilter('parent_id')) {
            $query->where('parent_id', (int) $parameters->filter('parent_id'));
        }

        return $parameters->paginate($query);
    }
}
