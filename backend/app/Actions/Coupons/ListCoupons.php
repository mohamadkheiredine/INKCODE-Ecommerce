<?php

namespace App\Actions\Coupons;

use App\Models\Coupon;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A page of Coupons, each with `times_used` (its redemptions not released) and the number
 * of eligible Products and Categories. The state filter compares the dates with today:
 * `scheduled` starts later, `expired` ended before today, `active` is neither.
 */
final class ListCoupons
{
    /**
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Coupon::query()
            ->withCount(['redemptions as times_used' => fn (Builder $query) => $query->whereNull('released_at')])
            ->withCount(['products', 'categories']);

        $parameters->search($query, ['code']);

        if ($parameters->hasFilter('type')) {
            $query->where('type', $parameters->filter('type'));
        }

        $today = Carbon::today()->toDateString();
        match ($parameters->filter('state')) {
            'scheduled' => $query->where('starts_on', '>', $today),
            'expired' => $query->where('ends_on', '<', $today),
            'active' => $query->where('starts_on', '<=', $today)
                ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today)),
            default => null,
        };

        return $parameters->paginate($query);
    }
}
