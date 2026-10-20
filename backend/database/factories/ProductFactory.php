<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * An Active, taxable Product in a new Category, without Brand, images or Variants.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(3, true)),
            'slug' => fn (array $attributes): string => Str::slug($attributes['name']),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'brand_id' => null,
            'base_price' => '25.00',
            'taxable' => true,
            'tags' => [],
            'seo_title' => null,
            'seo_description' => null,
            'status' => ProductStatus::Active,
        ];
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
