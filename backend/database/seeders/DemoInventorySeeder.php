<?php

namespace Database\Seeders;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\GenerateVariants;
use App\Actions\Inventory\CreateReceipt;
use App\Enums\ProductStatus;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * The demo store's Warehouses (three active, one deactivated), a few stocked Products and
 * the Receipts that stocked them, at two different unit costs so Average cost has been
 * re-weighted. A few Variants are left low or out of stock for the low-stock filter.
 * Everything goes through the same actions as the API, so balances equal their Stock
 * movements. Idempotent: Warehouses are restored by code, Products by slug, and the
 * Receipts are only recorded once.
 */
class DemoInventorySeeder extends Seeder
{
    /** @var array<string, array{name: string, address: string, active: bool}> */
    private const WAREHOUSES = [
        'BEY-01' => ['name' => 'Beirut Central', 'address' => 'Port of Beirut Free Zone, Beirut, Lebanon', 'active' => true],
        'TRI-01' => ['name' => 'Tripoli North', 'address' => 'Mina Industrial Area, Tripoli, Lebanon', 'active' => true],
        'DXB-01' => ['name' => 'Dubai Hub', 'address' => 'Jebel Ali Free Zone, Dubai, United Arab Emirates', 'active' => true],
        'JNH-01' => ['name' => 'Jounieh Overflow', 'address' => 'Sarba Highway, Jounieh, Lebanon', 'active' => false],
    ];

    /**
     * Each Product: its Category and Brand slugs, base price and the Attribute values its
     * Variants combine.
     *
     * @var array<string, array{name: string, category: string, brand: string, price: string, values: array<string, list<string>>}>
     */
    private const PRODUCTS = [
        'classic-cotton-tee' => [
            'name' => 'Classic Cotton Tee', 'category' => 'shirts', 'brand' => 'nordic-wear', 'price' => '19.90',
            'values' => ['Color' => ['Black', 'White', 'Navy'], 'Size' => ['S', 'M', 'L']],
        ],
        'trail-runner-sneaker' => [
            'name' => 'Trail Runner Sneaker', 'category' => 'sneakers', 'brand' => 'atlas-outdoor', 'price' => '89.00',
            'values' => ['Color' => ['Black', 'Olive'], 'Size' => ['M', 'L']],
        ],
        'leather-weekender-bag' => [
            'name' => 'Leather Weekender Bag', 'category' => 'bags', 'brand' => 'cedar-co', 'price' => '149.00',
            'values' => [],
        ],
        'ceramic-pour-over-set' => [
            'name' => 'Ceramic Pour-over Set', 'category' => 'kitchen', 'brand' => 'lumen-home', 'price' => '39.50',
            'values' => [],
        ],
    ];

    /**
     * The Receipts: Warehouse code, supplier reference, unit cost as a share of the price,
     * and the quantity for the n-th demo Variant (0 leaves it out).
     *
     * @var list<array{string, string, string, list<int>}>
     */
    private const RECEIPTS = [
        ['BEY-01', 'PO-2026-0412', '0.40', [40, 36, 30, 28, 24, 22, 18, 14, 3, 20, 16, 12, 10, 6, 25]],
        ['TRI-01', 'PO-2026-0418', '0.46', [12, 10, 8, 10, 8, 6, 0, 0, 0, 8, 6, 0, 0, 4, 12]],
        ['DXB-01', 'PO-2026-0425', '0.52', [6, 6, 0, 6, 6, 0, 4, 0, 0, 5, 0, 5, 0, 2, 0]],
    ];

    public function run(): void
    {
        $warehouses = [];
        foreach (self::WAREHOUSES as $code => $warehouse) {
            $warehouses[$code] = Warehouse::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $warehouse['name'], 'address' => $warehouse['address'], 'is_active' => $warehouse['active']],
            );
        }

        $variants = collect(array_keys(self::PRODUCTS))
            ->flatMap(fn (string $slug): array => $this->product($slug)->variants()->orderBy('position')->get()->all())
            ->values();

        if (Receipt::query()->where('supplier_reference', self::RECEIPTS[0][1])->exists()) {
            return;
        }

        foreach (self::RECEIPTS as [$code, $reference, $costShare, $quantities]) {
            $lines = [];
            foreach ($variants as $index => $variant) {
                /** @var Variant $variant */
                if (($quantities[$index] ?? 0) > 0) {
                    $lines[] = ['variant_id' => $variant->id, 'quantity' => $quantities[$index], 'unit_cost' => bcmul($variant->price, $costShare, 2)];
                }
            }

            app(CreateReceipt::class)->handle([
                'warehouse_id' => $warehouses[$code]->id,
                'supplier_reference' => $reference,
                'notes' => null,
                'lines' => $lines,
            ]);
        }
    }

    private function product(string $slug): Product
    {
        $existing = Product::query()->where('slug', $slug)->first();
        if ($existing !== null) {
            return $existing;
        }

        $definition = self::PRODUCTS[$slug];
        $product = app(CreateProduct::class)->handle([
            'name' => $definition['name'],
            'slug' => $slug,
            'description' => null,
            'category_id' => Category::query()->where('slug', $definition['category'])->value('id'),
            'brand_id' => Brand::query()->where('slug', $definition['brand'])->value('id'),
            'base_price' => $definition['price'],
            'taxable' => true,
            'tags' => [],
            'seo_title' => null,
            'seo_description' => null,
            'status' => ProductStatus::Active,
        ]);

        $valueIds = [];
        foreach ($definition['values'] as $attribute => $names) {
            $valueIds = [...$valueIds, ...AttributeValue::query()
                ->whereRelation('attribute', 'name', $attribute)
                ->whereIn('name', $names)
                ->pluck('id')
                ->all()];
        }
        app(GenerateVariants::class)->handle($product, $valueIds);

        return $product;
    }
}
