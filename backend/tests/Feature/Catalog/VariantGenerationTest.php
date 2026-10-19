<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Variant;

/**
 * @return array{color: Attribute, size: Attribute}
 */
function colorsAndSizes(): array
{
    return [
        'color' => Attribute::factory()->withValues(['Red', 'Blue', 'Black'])->create(['name' => 'Color']),
        'size' => Attribute::factory()->withValues(['S', 'M', 'L', 'XL'])->create(['name' => 'Size']),
    ];
}

/**
 * @param  list<string>  $names
 * @return list<int>
 */
function valueIds(Attribute $attribute, array $names = []): array
{
    return $attribute->values()
        ->when($names !== [], fn ($query) => $query->whereIn('name', $names))
        ->pluck('id')
        ->all();
}

describe('generating Variants', function () {
    it('creates every combination of the chosen values, with readable SKUs and the base price', function () {
        ['color' => $color, 'size' => $size] = colorsAndSizes();
        $product = Product::factory()->create(['name' => 'Classic Tee', 'base_price' => '19.90']);

        $response = $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", [
                'attribute_value_ids' => [...valueIds($color), ...valueIds($size)],
            ])
            ->assertOk()
            ->assertJsonPath('meta.generated', 12)
            ->assertJsonCount(12, 'data.variants');

        expect($response->json('data.variants.0'))->toMatchArray([
            'sku' => 'CLASSIC-TEE-RED-S',
            'price' => '19.90',
            'average_cost' => '0.0000',
            'low_stock_threshold' => null,
            'options' => [['attribute' => 'Color', 'value' => 'Red'], ['attribute' => 'Size', 'value' => 'S']],
        ])
            ->and($response->json('data.variants.*.sku'))->toContain('CLASSIC-TEE-BLACK-XL', 'CLASSIC-TEE-BLUE-M')
            ->and($response->json('data.attributes'))->toBe([
                ['id' => $color->id, 'name' => 'Color', 'value_ids' => valueIds($color)],
                ['id' => $size->id, 'name' => 'Size', 'value_ids' => valueIds($size)],
            ]);
    });

    it('creates only the missing combinations and keeps existing Variants and their data', function () {
        ['color' => $color, 'size' => $size] = colorsAndSizes();
        $product = Product::factory()->create(['name' => 'Classic Tee']);
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", [
                'attribute_value_ids' => [...valueIds($color, ['Red']), ...valueIds($size, ['S', 'M'])],
            ])
            ->assertJsonPath('meta.generated', 2);
        $kept = Variant::query()->where('sku', 'CLASSIC-TEE-RED-S')->sole();
        $kept->update(['sku' => 'TEE-RED-SMALL', 'price' => '24.00']);

        $this->postJson("/api/v1/products/{$product->id}/variants/generate", [
            'attribute_value_ids' => [...valueIds($color, ['Red', 'Blue']), ...valueIds($size, ['S', 'M'])],
        ])
            ->assertOk()
            ->assertJsonPath('meta.generated', 2)
            ->assertJsonCount(4, 'data.variants');

        expect($kept->fresh()?->only('id', 'sku', 'price'))->toBe(['id' => $kept->id, 'sku' => 'TEE-RED-SMALL', 'price' => '24.00']);
    });

    it('gives a simple Product without Attributes one Variant', function () {
        $product = Product::factory()->create(['name' => 'Gift Card']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => []])
            ->assertOk()
            ->assertJsonPath('meta.generated', 1)
            ->assertJsonPath('data.variants.0.sku', 'GIFT-CARD')
            ->assertJsonPath('data.variants.0.options', []);
    });

    it('makes a generated SKU unique when another Variant already uses it', function () {
        ['color' => $color] = colorsAndSizes();
        Variant::factory()->create(['sku' => 'CLASSIC-TEE-RED']);
        $product = Product::factory()->create(['name' => 'Classic Tee']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => valueIds($color, ['Red'])])
            ->assertJsonPath('data.variants.0.sku', 'CLASSIC-TEE-RED-2');
    });

    it('refuses values of other Attributes than the ones the existing Variants differ by', function () {
        ['color' => $color, 'size' => $size] = colorsAndSizes();
        $product = Product::factory()->create();
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => valueIds($color)])
            ->assertOk();

        $this->postJson("/api/v1/products/{$product->id}/variants/generate", [
            'attribute_value_ids' => [...valueIds($color), ...valueIds($size)],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attribute_value_ids' => "This Product's Variants differ by Color. Choose values of exactly these Attributes, or delete its Variants first."]);

        expect($product->variants()->count())->toBe(3);
    });

    it('refuses values that do not exist', function () {
        $product = Product::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attribute_value_ids.0');
    });

    it('records each new Variant in the audit trail', function () {
        ['color' => $color] = colorsAndSizes();
        $product = Product::factory()->create(['name' => 'Classic Tee', 'base_price' => '19.90']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => valueIds($color, ['Red'])]);

        $entry = auditTrailOf('variant', Variant::query()->sole()->id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->record_label)->toBe('CLASSIC-TEE-RED')
            ->and($entry->after)->toMatchArray(['sku' => 'CLASSIC-TEE-RED', 'price' => '19.90', 'product_id' => $product->id]);
    });

    it('refuses Roles that may not change the catalog', function () {
        ['color' => $color] = colorsAndSizes();
        $product = Product::factory()->create();

        $this->actingAs(userWithRole(Role::Inventory))
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => valueIds($color)])
            ->assertForbidden();

        expect($product->variants()->count())->toBe(0);
    });
});
