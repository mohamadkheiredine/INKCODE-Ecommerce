<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * An active method delivering in two to four days, in a zone of its own.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipping_zone_id' => ShippingZone::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'price' => '5.00',
            'min_delivery_days' => 2,
            'max_delivery_days' => 4,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
