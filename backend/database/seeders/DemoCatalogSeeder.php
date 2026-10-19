<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The demo store's Categories (two levels) and Brands. Idempotent: running it again
 * restores these records by slug and leaves anything Users added alone. The demo data
 * ticket (#28) builds Products on top of them.
 */
class DemoCatalogSeeder extends Seeder
{
    /** @var array<string, array{name: string, description: string, children: array<string, string>}> */
    private const CATEGORIES = [
        'clothing' => [
            'name' => 'Clothing',
            'description' => 'Everyday and seasonal clothes for women and men.',
            'children' => ['shirts' => 'Shirts', 'trousers' => 'Trousers', 'jackets' => 'Jackets', 'dresses' => 'Dresses'],
        ],
        'footwear' => [
            'name' => 'Footwear',
            'description' => 'Shoes for the city, the office and the trail.',
            'children' => ['sneakers' => 'Sneakers', 'boots' => 'Boots', 'sandals' => 'Sandals'],
        ],
        'accessories' => [
            'name' => 'Accessories',
            'description' => 'Bags, hats and the finishing touches.',
            'children' => ['bags' => 'Bags', 'hats' => 'Hats', 'watches' => 'Watches'],
        ],
        'home' => [
            'name' => 'Home',
            'description' => 'Kitchen and decor pieces.',
            'children' => ['kitchen' => 'Kitchen', 'decor' => 'Decor'],
        ],
    ];

    /** @var array<string, string> */
    private const BRANDS = [
        'nordic-wear' => 'Nordic Wear',
        'atlas-outdoor' => 'Atlas Outdoor',
        'urban-thread' => 'Urban Thread',
        'zest' => 'Zest',
        'lumen-home' => 'Lumen Home',
        'cedar-co' => 'Cedar & Co.',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $slug => $category) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $category['name'], 'description' => $category['description'], 'parent_id' => null],
            );

            foreach ($category['children'] as $childSlug => $childName) {
                Category::query()->updateOrCreate(['slug' => $childSlug], ['name' => $childName, 'parent_id' => $parent->id]);
            }
        }

        foreach (self::BRANDS as $slug => $name) {
            Brand::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
