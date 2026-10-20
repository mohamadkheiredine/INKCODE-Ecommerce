<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ListRequest;
use App\Models\Attribute;

/**
 * The Attribute library: search by name, sort by name or date.
 */
class ListAttributesRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Attribute::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
