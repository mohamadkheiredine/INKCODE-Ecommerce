<?php

namespace App\Http\Requests\Shipping;

use App\Http\Requests\ListRequest;
use App\Models\ShippingZone;
use App\Support\Countries;

/**
 * The Shipping zones list: search by name, filter by a country the zone covers (wholly or
 * through one of its cities), sort by name or date.
 */
class ListShippingZonesRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ShippingZone::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter');
        if (is_array($filter) && array_key_exists('country', $filter)) {
            $this->merge(['filter' => [...$filter, 'country' => Countries::normalize($filter['country'])]]);
        }
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
        return ['country' => ['string', Countries::rule()]];
    }
}
