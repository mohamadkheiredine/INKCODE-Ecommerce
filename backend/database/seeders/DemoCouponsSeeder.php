<?php

namespace Database\Seeders;

use App\Actions\Coupons\SaveCoupon;
use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Demo Coupons, one of each kind a reviewer may try: an active percentage Coupon (used by
 * some demo Orders), a fixed-amount welcome Coupon with a minimum and one use per
 * Customer, one for a single Category, an expired one and a scheduled one. Dates are
 * relative to today, so they stay meaningful. Created by the demo Sales User through the
 * same action as the API, and only once (skipped when Coupons exist).
 */
class DemoCouponsSeeder extends Seeder
{
    public function run(): void
    {
        if (Coupon::query()->exists()) {
            return;
        }

        $today = Carbon::today();
        $category = Category::query()->whereNull('parent_id')->orderBy('id')->first();
        $coupons = [
            ['code' => 'SAVE10', 'type' => CouponType::Percentage, 'value' => '10.00', 'starts_on' => $today->copy()->subDays(90), 'ends_on' => null, 'usage_limit' => 500, 'per_customer_limit' => null, 'minimum_order_amount' => '0', 'category_ids' => []],
            ['code' => 'WELCOME5', 'type' => CouponType::Fixed, 'value' => '5.00', 'starts_on' => $today->copy()->subDays(60), 'ends_on' => $today->copy()->addDays(60), 'usage_limit' => null, 'per_customer_limit' => 1, 'minimum_order_amount' => '25.00', 'category_ids' => []],
            ['code' => 'CATEGORY15', 'type' => CouponType::Percentage, 'value' => '15.00', 'starts_on' => $today->copy()->subDays(30), 'ends_on' => $today->copy()->addDays(30), 'usage_limit' => 100, 'per_customer_limit' => 2, 'minimum_order_amount' => '0', 'category_ids' => $category === null ? [] : [$category->id]],
            ['code' => 'SUMMER20', 'type' => CouponType::Percentage, 'value' => '20.00', 'starts_on' => $today->copy()->subDays(150), 'ends_on' => $today->copy()->subDays(60), 'usage_limit' => null, 'per_customer_limit' => null, 'minimum_order_amount' => '50.00', 'category_ids' => []],
            ['code' => 'LAUNCH25', 'type' => CouponType::Fixed, 'value' => '25.00', 'starts_on' => $today->copy()->addDays(14), 'ends_on' => $today->copy()->addDays(44), 'usage_limit' => 50, 'per_customer_limit' => 1, 'minimum_order_amount' => '100.00', 'category_ids' => []],
        ];

        Auth::setUser(User::query()->where('email', 'sales@inkcode.test')->firstOrFail());
        try {
            foreach ($coupons as $coupon) {
                app(SaveCoupon::class)->handle(null, [
                    ...$coupon,
                    'starts_on' => $coupon['starts_on']->toDateString(),
                    'ends_on' => $coupon['ends_on']?->toDateString(),
                    'product_ids' => [],
                ]);
            }
        } finally {
            Auth::forgetUser();
        }
    }
}
