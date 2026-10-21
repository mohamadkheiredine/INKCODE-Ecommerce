<?php

namespace App\Actions\Coupons;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A Coupon's usage: one row per Order that used it, with its Customer, discount and
 * whether the use still counts (released when the Order was fully cancelled).
 */
final class ListCouponRedemptions
{
    /**
     * @return LengthAwarePaginator<int, CouponRedemption>
     */
    public function handle(Coupon $coupon, ListParameters $parameters): LengthAwarePaginator
    {
        $query = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->with(['order:id,number,status,total,currency_code', 'customer:id,name,email']);

        match ($parameters->filter('state')) {
            'counted' => $query->whereNull('released_at'),
            'released' => $query->whereNotNull('released_at'),
            default => null,
        };

        return $parameters->paginate($query);
    }
}
