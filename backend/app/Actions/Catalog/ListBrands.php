<?php

namespace App\Actions\Catalog;

use App\Models\Brand;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Brands.
 */
final class ListBrands
{
    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Brand::query();

        $parameters->search($query, ['name', 'slug']);

        if ($parameters->hasFilter('logo')) {
            $parameters->filter('logo') === 'with'
                ? $query->whereNotNull('logo_path')
                : $query->whereNull('logo_path');
        }

        return $parameters->paginate($query);
    }
}
