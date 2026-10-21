<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;

/**
 * A Coupon {@see CouponValidator} accepted for a set of Order lines, with the Variants its
 * discount applies to.
 */
final readonly class AppliedCoupon
{
    /**
     * @param  list<int>  $eligibleVariantIds
     */
    public function __construct(
        public Coupon $coupon,
        public array $eligibleVariantIds,
    ) {}

    public function isEligible(int $variantId): bool
    {
        return in_array($variantId, $this->eligibleVariantIds, true);
    }

    /**
     * What {@see OrderPricing::reprice()} needs to discount the eligible lines.
     *
     * @return array{type: CouponType, value: string}
     */
    public function terms(): array
    {
        return ['type' => $this->coupon->type, 'value' => $this->coupon->value];
    }

    /**
     * The snapshot an Order keeps of its Coupon.
     *
     * @return array{coupon_id: int, coupon_code: string, coupon_type: CouponType, coupon_value: string}
     */
    public function orderSnapshot(): array
    {
        return [
            'coupon_id' => $this->coupon->id,
            'coupon_code' => $this->coupon->code,
            'coupon_type' => $this->coupon->type,
            'coupon_value' => $this->coupon->value,
        ];
    }

    /**
     * The snapshot of an Order without a Coupon.
     *
     * @return array{coupon_id: null, coupon_code: null, coupon_type: null, coupon_value: null}
     */
    public static function none(): array
    {
        return ['coupon_id' => null, 'coupon_code' => null, 'coupon_type' => null, 'coupon_value' => null];
    }
}
