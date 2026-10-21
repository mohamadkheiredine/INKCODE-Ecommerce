<?php

namespace App\Http\Requests\Coupons;

use App\Enums\CouponType;
use App\Http\Requests\ListRequest;
use App\Models\Coupon;
use Illuminate\Validation\Rule;

/**
 * The Coupons list: search by code, filter by type and by state (`active`, `scheduled` or
 * `expired` today), sort by code, dates, use count or date created.
 */
class ListCouponsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Coupon::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['code', 'starts_on', 'ends_on', 'times_used', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filters(): array
    {
        return [
            'type' => ['string', Rule::enum(CouponType::class)],
            'state' => ['string', 'in:active,scheduled,expired'],
        ];
    }
}
