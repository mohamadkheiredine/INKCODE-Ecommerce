<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    /**
     * A Variant without Attribute values (the only Variant of a simple Product).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => Str::upper(fake()->unique()->bothify('SKU-####-??')),
            'combination_key' => '',
            'price' => '25.00',
            'average_cost' => '10.0000',
            'low_stock_threshold' => null,
            'position' => 0,
        ];
    }
}
