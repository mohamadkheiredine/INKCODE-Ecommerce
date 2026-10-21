<?php

namespace App\Http\Requests\Coupons;

/**
 * A Coupon's new terms. Orders that already use it keep the terms they were priced with.
 */
class UpdateCouponRequest extends StoreCouponRequest
{
    public function authorize(): bool
    {
        $coupon = $this->coupon();

        return $coupon !== null && ($this->user()?->can('update', $coupon) ?? false);
    }
}
