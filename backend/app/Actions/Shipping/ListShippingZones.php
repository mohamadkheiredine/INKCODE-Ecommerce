<?php

namespace App\Actions\Shipping;

use App\Models\ShippingZone;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of Shipping zones with the places they cover and how many methods they offer.
 */
final class ListShippingZones
{
    /**
     * @return LengthAwarePaginator<int, ShippingZone>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = ShippingZone::query()
            ->with('locations')
            ->withCount([
                'methods',
                'methods as active_methods_count' => fn (Builder $methods) => $methods->where('is_active', true),
            ]);

        $parameters->search($query, ['name']);

        if ($parameters->hasFilter('country')) {
            $query->whereHas('locations', fn (Builder $locations) => $locations->where('country_code', $parameters->filter('country')));
        }

        return $parameters->paginate($query);
    }
}
