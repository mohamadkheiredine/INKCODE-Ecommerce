<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ListRequest;
use App\Models\Brand;

/**
 * The Brands list: search by name or slug, filter by whether a logo is set (`with` or
 * `without`), sort by name or date.
 */
class ListBrandsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Brand::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filters(): array
    {
        return ['logo' => ['string', 'in:with,without']];
    }
}
