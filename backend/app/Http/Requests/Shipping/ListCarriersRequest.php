<?php

namespace App\Http\Requests\Shipping;

use App\Http\Requests\ListRequest;
use App\Models\Carrier;

/**
 * The Carriers list: search by name, filter by whether a tracking link is set (`with` or
 * `without`), sort by name or date.
 */
class ListCarriersRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Carrier::class) ?? false;
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
        return ['tracking' => ['string', 'in:with,without']];
    }
}
