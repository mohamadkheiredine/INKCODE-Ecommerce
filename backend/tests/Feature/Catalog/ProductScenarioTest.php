<?php

use App\Enums\Role;
use App\Models\Attribute;
use App\Models\Category;

/*
 * Demo scenario 1, first half: a Product with 3 Colors × 4 Sizes, one Variant edited,
 * then found again through the list's filters.
 */
it('creates a 3 × 4 Product, edits one Variant, and finds it by filtering', function () {
    $color = Attribute::factory()->withValues(['Red', 'Blue', 'Black'])->create(['name' => 'Color']);
    $size = Attribute::factory()->withValues(['S', 'M', 'L', 'XL'])->create(['name' => 'Size']);
    $shirts = Category::factory()->create(['name' => 'Shirts']);
    $this->actingAs(userWithRole(Role::Operations));

    $productId = $this->postJson('/api/v1/products', [
        'name' => 'Classic Tee', 'category_id' => $shirts->id, 'base_price' => '19.90', 'taxable' => true,
        'tags' => [], 'status' => 'active',
    ])->assertCreated()->json('data.id');

    $variants = $this->postJson("/api/v1/products/$productId/variants/generate", [
        'attribute_value_ids' => [...$color->values->modelKeys(), ...$size->values->modelKeys()],
    ])->assertOk()->json('data.variants');
    expect($variants)->toHaveCount(12)
        ->and(collect($variants)->pluck('sku')->unique())->toHaveCount(12);

    $blueM = collect($variants)->firstWhere('sku', 'CLASSIC-TEE-BLUE-M');
    $this->putJson("/api/v1/products/$productId/variants", ['variants' => [[
        ...$blueM, 'sku' => 'TEE-BLUE-M-LTD', 'price' => '29.00',
    ]]])->assertOk();

    $this->getJson("/api/v1/products?search=TEE-BLUE-M-LTD&filter[category_id]={$shirts->id}&filter[status]=active&filter[price_min]=25")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Classic Tee')
        ->assertJsonPath('data.0.variants_count', 12)
        ->assertJsonPath('data.0.price_min', '19.90')
        ->assertJsonPath('data.0.price_max', '29.00');
});
