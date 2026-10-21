<?php

namespace App\Enums;

/**
 * How a Coupon discounts an Order: a percentage of the eligible lines, or a fixed amount
 * (never more than the eligible lines are worth).
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
