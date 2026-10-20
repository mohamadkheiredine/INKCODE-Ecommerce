<?php

namespace App\Actions\Customers;

use App\Models\Tag;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Tags, for the Customers filter and the Tag picker.
 */
final class ListTags
{
    /**
     * @return LengthAwarePaginator<int, Tag>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Tag::query();

        $parameters->search($query, ['name']);

        return $parameters->paginate($query);
    }
}
