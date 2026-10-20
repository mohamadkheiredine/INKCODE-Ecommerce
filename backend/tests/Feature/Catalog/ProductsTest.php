<?php

use App\Enums\AuditAction;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productInput(array $overrides = []): array
{
    return [
        'name' => 'Classic Tee',
        'slug' => '',
        'description' => 'A soft cotton tee.',
        'category_id' => Category::factory()->create()->id,
        'brand_id' => null,
        'base_price' => '19.90',
        'taxable' => true,
        'tags' => ['cotton', 'summer'],
        'seo_title' => 'Classic Tee',
        'seo_description' => 'Soft cotton.',
        'status' => 'draft',
        ...$overrides,
    ];
}

describe('creating a Product', function () {
    it('creates a Product with a generated slug and records it in the audit trail', function () {
        $brand = Brand::factory()->create(['name' => 'Nordic Wear']);

        $id = $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/products', productInput(['brand_id' => $brand->id]))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'classic-tee')
            ->assertJsonPath('data.base_price', '19.90')
            ->assertJsonPath('data.brand.name', 'Nordic Wear')
            ->assertJsonPath('data.tags', ['cotton', 'summer'])
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.variants', [])
            ->json('data.id');

        $entry = auditTrailOf('product', $id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->after)->toMatchArray(['name' => 'Classic Tee', 'base_price' => '19.90', 'status' => 'draft']);
    });

    it('validates every field', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/products', productInput([
                'name' => '',
                'category_id' => 999,
                'base_price' => '12.345',
                'tags' => ['ok', str_repeat('x', 41)],
                'status' => 'published',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'category_id', 'base_price', 'tags.1', 'status']);
    });

    it('refuses a negative price', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/products', productInput(['base_price' => '-1.00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_price');
    });

    it('refuses Roles that may not change the catalog', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/products', productInput())
            ->assertForbidden();

        expect(Product::query()->count())->toBe(0);
    });
});

describe('one Product', function () {
    it('shows a Product with its Variants to every Role', function () {
        $product = Product::factory()->create();
        Variant::factory()->for($product)->create(['sku' => 'GIFT']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.variants.0.sku', 'GIFT')
            ->assertJsonPath('data.attributes', []);
    });

    it('saves changes and records only what changed', function () {
        $product = Product::factory()->create(['name' => 'Classic Tee', 'base_price' => '19.90', 'status' => ProductStatus::Draft]);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$product->id}", productInput([
                'name' => 'Classic Tee',
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'base_price' => '21.00',
                'status' => 'active',
                'tags' => [],
                'seo_title' => null,
                'seo_description' => null,
            ]))
            ->assertOk()
            ->assertJsonPath('data.base_price', '21.00')
            ->assertJsonPath('data.status', 'active');

        expect(auditTrailOf('product', $product->id)->sole()->only('before', 'after'))->toEqual([
            'before' => ['base_price' => '19.90', 'status' => 'draft'],
            'after' => ['base_price' => '21.00', 'status' => 'active'],
        ]);
    });

    it('deletes a Product with its Variants and records it', function () {
        $product = Product::factory()->create();
        Variant::factory()->for($product)->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertNoContent();

        expect(Product::query()->count())->toBe(0)
            ->and(Variant::query()->count())->toBe(0)
            ->and(auditTrailOf('product', $product->id)->sole()->action)->toBe(AuditAction::Deleted);
    });

    it('refuses to delete for Roles that may not change the catalog', function () {
        $product = Product::factory()->create();

        $this->actingAs(userWithRole(Role::Viewer))
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertForbidden();
    });

    it('keeps a Category or Brand that Products use from being deleted', function () {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);
        $this->actingAs(userWithRole(Role::Operations));

        $this->deleteJson("/api/v1/brands/{$brand->id}")->assertConflict()->assertJsonPath('code', 'in_use');
        $this->deleteJson("/api/v1/categories/{$product->category_id}")->assertConflict()->assertJsonPath('code', 'in_use');
    });
});

describe('the Products list', function () {
    beforeEach(function () {
        $this->shirts = Category::factory()->create(['name' => 'Shirts']);
        $this->clothing = Category::factory()->create(['name' => 'Clothing']);
        $this->shirts->update(['parent_id' => $this->clothing->id]);
        $this->nordic = Brand::factory()->create();

        $tee = Product::factory()->create(['name' => 'Classic Tee', 'category_id' => $this->shirts->id, 'brand_id' => $this->nordic->id, 'base_price' => '20.00']);
        Variant::factory()->for($tee)->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
        Variant::factory()->for($tee)->create(['sku' => 'TEE-RED-L', 'price' => '24.00', 'combination_key' => '2']);
        $boots = Product::factory()->create(['name' => 'Trail Boots', 'base_price' => '120.00', 'status' => ProductStatus::Draft]);
        Variant::factory()->for($boots)->create(['sku' => 'BOOT-42', 'price' => '120.00']);
        Product::factory()->create(['name' => 'Archived Mug', 'base_price' => '8.00', 'status' => ProductStatus::Archived]);
        $this->actingAs(userWithRole(Role::Viewer));
    });

    it('lists Products with their main facts, a page at a time', function () {
        $this->getJson('/api/v1/products?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.name', 'Archived Mug')
            ->assertJsonPath('data.1.name', 'Classic Tee')
            ->assertJsonPath('data.1.variants_count', 2)
            ->assertJsonPath('data.1.price_min', '20.00')
            ->assertJsonPath('data.1.price_max', '24.00')
            ->assertJsonPath('data.1.category.name', 'Shirts')
            ->assertJsonPath('data.1.main_image_url', null);
    });

    it('searches by name or Variant SKU', function () {
        expect($this->getJson('/api/v1/products?search=boots')->json('data.*.name'))->toBe(['Trail Boots'])
            ->and($this->getJson('/api/v1/products?search=TEE-RED-L')->json('data.*.name'))->toBe(['Classic Tee']);
    });

    it('filters by Category (a top-level Category includes its children), Brand and status', function () {
        expect($this->getJson("/api/v1/products?filter[category_id]={$this->clothing->id}")->json('data.*.name'))->toBe(['Classic Tee'])
            ->and($this->getJson("/api/v1/products?filter[brand_id]={$this->nordic->id}")->json('data.*.name'))->toBe(['Classic Tee'])
            ->and($this->getJson('/api/v1/products?filter[status]=draft')->json('data.*.name'))->toBe(['Trail Boots']);
    });

    it('filters by price range: any Variant priced within it, or the base price without Variants', function () {
        expect($this->getJson('/api/v1/products?filter[price_min]=22&filter[price_max]=100')->json('data.*.name'))->toBe(['Classic Tee'])
            ->and($this->getJson('/api/v1/products?filter[price_max]=10')->json('data.*.name'))->toBe(['Archived Mug']);
    });

    it('sorts by price and date', function () {
        expect($this->getJson('/api/v1/products?sort=-price')->json('data.*.name'))->toBe(['Trail Boots', 'Classic Tee', 'Archived Mug'])
            ->and($this->getJson('/api/v1/products?sort=-created_at')->json('data.*.name'))->toBe(['Archived Mug', 'Trail Boots', 'Classic Tee']);
    });

    it('refuses an unknown filter', function () {
        $this->getJson('/api/v1/products?filter[colour]=red')->assertUnprocessable();
    });
});
