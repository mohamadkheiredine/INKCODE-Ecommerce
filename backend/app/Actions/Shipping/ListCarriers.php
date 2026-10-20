<?php

namespace App\Actions\Shipping;

use App\Models\Carrier;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of Carriers.
 */
final class ListCarriers
{
    /**
     * @return LengthAwarePaginator<int, Carrier>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Carrier::query();

        $parameters->search($query, ['name']);

        if ($parameters->hasFilter('tracking')) {
            $parameters->filter('tracking') === 'with'
                ? $query->whereNotNull('tracking_url_template')
                : $query->whereNull('tracking_url_template');
        }

        return $parameters->paginate($query);
    }
}
