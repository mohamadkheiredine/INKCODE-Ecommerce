<?php

namespace App\Http\Requests\Coupons;

use App\Http\Requests\ListRequest;
use App\Models\Coupon;

/**
 * A Coupon's usage: the Orders and Customers that used it, newest first, optionally only
 * the uses still counted (`filter[state]=counted`) or released (`released`).
 */
class ListCouponRedemptionsRequest extends ListRequest
{
    public function authorize(): bool
    {
        $coupon = $this->route('coupon');

        return $coupon instanceof Coupon && ($this->user()?->can('view', $coupon) ?? false);
    }

    protected function sorts(): array
    {
        return ['redeemed_at', 'discount_amount'];
    }

    protected function defaultSort(): string
    {
        return '-redeemed_at';
    }

    protected function filters(): array
    {
        return ['state' => ['string', 'in:counted,released']];
    }
}
