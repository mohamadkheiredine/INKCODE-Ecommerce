<?php

namespace App\Actions\Coupons;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Coupon, or changes one, with its eligible Products and Categories, and records
 * the change in the audit log. Orders that already use it keep the terms they were priced
 * with.
 */
final class SaveCoupon
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{code: string, type: CouponType, value: string, starts_on: string, ends_on: string|null, usage_limit: int|null, per_customer_limit: int|null, minimum_order_amount: string, product_ids: list<int>, category_ids: list<int>}  $values
     */
    public function handle(?Coupon $coupon, array $values): Coupon
    {
        return DB::transaction(function () use ($coupon, $values): Coupon {
            $before = $coupon?->auditValues();
            $coupon ??= new Coupon;

            $coupon->fill(array_diff_key($values, array_flip(['product_ids', 'category_ids'])))->save();
            $coupon->products()->sync($values['product_ids']);
            $coupon->categories()->sync($values['category_ids']);

            $before === null
                ? $this->auditTrail->created($coupon)
                : $this->auditTrail->updated($coupon, $before);

            return $coupon;
        });
    }
}
