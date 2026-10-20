<?php

use App\Enums\AuditAction;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\Product;

describe('bulk actions on Products', function () {
    it('activates the chosen Products and records each one that changed', function () {
        $draft = Product::factory()->status(ProductStatus::Draft)->create();
        $active = Product::factory()->status(ProductStatus::Active)->create();
        $untouched = Product::factory()->status(ProductStatus::Draft)->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/products/bulk', ['action' => 'activate', 'product_ids' => [$draft->id, $active->id]])
            ->assertOk()
            ->assertJsonPath('data', ['changed' => 1, 'deleted' => 0, 'skipped' => []]);

        expect($draft->fresh()?->status)->toBe(ProductStatus::Active)
            ->and($untouched->fresh()?->status)->toBe(ProductStatus::Draft)
            ->and(auditTrailOf('product', $draft->id)->sole()->after)->toBe(['status' => 'active'])
            ->and(auditTrailOf('product', $active->id))->toBeEmpty();
    });

    it('archives the chosen Products', function () {
        $products = Product::factory()->count(2)->create();

        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/products/bulk', ['action' => 'archive', 'product_ids' => $products->modelKeys()])
            ->assertJsonPath('data.changed', 2);

        expect(Product::query()->where('status', ProductStatus::Archived)->count())->toBe(2);
    });

    it('deletes the chosen Products and records each deletion', function () {
        $products = Product::factory()->count(2)->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/products/bulk', ['action' => 'delete', 'product_ids' => $products->modelKeys()])
            ->assertJsonPath('data.deleted', 2);

        expect(Product::query()->count())->toBe(0)
            ->and(auditTrailOf('product', $products->first()->id)->sole()->action)->toBe(AuditAction::Deleted);
    });

    it('validates the action and the Products', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/products/bulk', ['action' => 'publish', 'product_ids' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action', 'product_ids.0']);
    });

    it('refuses Roles that may not change the catalog', function () {
        $product = Product::factory()->create();

        $this->actingAs(userWithRole(Role::Viewer))
            ->postJson('/api/v1/products/bulk', ['action' => 'delete', 'product_ids' => [$product->id]])
            ->assertForbidden();

        expect($product->fresh())->not->toBeNull();
    });
});
