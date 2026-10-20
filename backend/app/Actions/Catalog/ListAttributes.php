<?php

namespace App\Actions\Catalog;

use App\Models\Attribute;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Attributes, each with its values in order.
 */
final class ListAttributes
{
    /**
     * @return LengthAwarePaginator<int, Attribute>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Attribute::query()->with('values');

        $parameters->search($query, ['name']);

        return $parameters->paginate($query);
    }
}
