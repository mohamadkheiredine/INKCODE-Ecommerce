<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * A 10% Coupon valid since yesterday with no end, no limits, no minimum and every line
     * eligible.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(10)),
            'type' => CouponType::Percentage,
            'value' => '10.00',
            'starts_on' => Carbon::yesterday()->toDateString(),
            'ends_on' => null,
            'usage_limit' => null,
            'per_customer_limit' => null,
            'minimum_order_amount' => '0.00',
        ];
    }

    public function fixed(string $amount): static
    {
        return $this->state(['type' => CouponType::Fixed, 'value' => $amount]);
    }
}
