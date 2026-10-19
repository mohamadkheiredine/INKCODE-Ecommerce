<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Product;
use App\Models\Variant;

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->red = Variant::factory()->for($this->product)->create(['sku' => 'TEE-RED', 'price' => '19.90', 'combination_key' => '1']);
    $this->blue = Variant::factory()->for($this->product)->create(['sku' => 'TEE-BLUE', 'price' => '19.90', 'combination_key' => '2']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variantRow(Variant $variant, array $overrides = []): array
{
    return [
        'id' => $variant->id,
        'sku' => $variant->sku,
        'price' => $variant->price,
        'average_cost' => $variant->average_cost,
        'low_stock_threshold' => $variant->low_stock_threshold,
        ...$overrides,
    ];
}

describe('editing Variants', function () {
    it("saves each Variant's SKU, price, Average cost and Low-stock threshold and records what changed", function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [
                variantRow($this->red, ['sku' => 'TEE-RED-SPECIAL', 'price' => '24.50', 'average_cost' => '8.125', 'low_stock_threshold' => 3]),
                variantRow($this->blue),
            ]])
            ->assertOk()
            ->assertJsonPath('data.variants.0.sku', 'TEE-RED-SPECIAL')
            ->assertJsonPath('data.variants.0.price', '24.50')
            ->assertJsonPath('data.variants.0.average_cost', '8.1250')
            ->assertJsonPath('data.variants.0.low_stock_threshold', 3);

        expect(auditTrailOf('variant', $this->red->id)->sole()->only('action', 'record_label', 'after'))->toEqual([
            'action' => AuditAction::Updated,
            'record_label' => 'TEE-RED-SPECIAL',
            'after' => ['sku' => 'TEE-RED-SPECIAL', 'price' => '24.50', 'average_cost' => '8.1250', 'low_stock_threshold' => 3],
        ])
            ->and(auditTrailOf('variant', $this->blue->id))->toBeEmpty();
    });

    it('refuses a SKU another Variant already has, with a clear message', function () {
        Variant::factory()->create(['sku' => 'TAKEN-1']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [
                variantRow($this->red, ['sku' => 'TAKEN-1']),
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.0.sku' => 'Another Variant already uses the SKU TAKEN-1.']);

        expect($this->red->fresh()?->sku)->toBe('TEE-RED');
    });

    it('refuses the same SKU twice in one save', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [
                variantRow($this->red, ['sku' => 'TEE-ONE']),
                variantRow($this->blue, ['sku' => 'TEE-ONE']),
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.0.sku', 'variants.1.sku']);
    });

    it('lets two Variants swap SKUs', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [
                variantRow($this->red, ['sku' => 'TEE-BLUE']),
                variantRow($this->blue, ['sku' => 'TEE-RED']),
            ]])
            ->assertOk();

        expect($this->red->fresh()?->sku)->toBe('TEE-BLUE');
    });

    it('validates SKU format, amounts and thresholds', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [
                variantRow($this->red, ['sku' => 'tee red', 'price' => '1.999', 'average_cost' => '-1', 'low_stock_threshold' => -2]),
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.0.sku', 'variants.0.price', 'variants.0.average_cost', 'variants.0.low_stock_threshold']);
    });

    it('refuses a Variant of another Product', function () {
        $other = Variant::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [variantRow($other, ['price' => '1.00'])]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.id');
    });

    it('refuses Roles that may not change the catalog', function () {
        $this->actingAs(userWithRole(Role::Finance))
            ->putJson("/api/v1/products/{$this->product->id}/variants", ['variants' => [variantRow($this->red, ['price' => '1.00'])]])
            ->assertForbidden();
    });
});

describe('removing a Variant', function () {
    it('deletes the Variant and records it', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/products/{$this->product->id}/variants/{$this->red->id}")
            ->assertNoContent();

        expect($this->product->variants()->pluck('sku')->all())->toBe(['TEE-BLUE'])
            ->and(auditTrailOf('variant', $this->red->id)->sole()->action)->toBe(AuditAction::Deleted);
    });

    it("answers 404 for another Product's Variant", function () {
        $other = Variant::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/products/{$this->product->id}/variants/{$other->id}")
            ->assertNotFound();
    });

    it('refuses Roles that may not change the catalog', function () {
        $this->actingAs(userWithRole(Role::Support))
            ->deleteJson("/api/v1/products/{$this->product->id}/variants/{$this->red->id}")
            ->assertForbidden();
    });
});
