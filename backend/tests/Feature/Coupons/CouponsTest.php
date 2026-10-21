<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function couponPayload(array $overrides = []): array
{
    return [
        'code' => 'save10',
        'type' => 'percentage',
        'value' => '10',
        'starts_on' => Carbon::today()->toDateString(),
        'ends_on' => Carbon::today()->addDays(30)->toDateString(),
        'usage_limit' => 100,
        'per_customer_limit' => 1,
        'minimum_order_amount' => '20.00',
        'product_ids' => [],
        'category_ids' => [],
        ...$overrides,
    ];
}

describe('listing Coupons', function () {
    it('lists Coupons with their state and uses, searchable and filtered by type and state', function () {
        Coupon::factory()->create(['code' => 'ACTIVE10', 'created_at' => now()->subMinutes(3)]);
        Coupon::factory()->fixed('5.00')->create(['code' => 'OLD5', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01', 'created_at' => now()->subMinutes(2)]);
        Coupon::factory()->create(['code' => 'LATER', 'starts_on' => Carbon::tomorrow()->toDateString(), 'created_at' => now()->subMinute()]);

        $this->actingAs(userWithRole(Role::Finance))
            ->getJson('/api/v1/coupons')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.code', 'LATER')
            ->assertJsonPath('data.0.state', 'scheduled')
            ->assertJsonPath('data.1.state', 'expired')
            ->assertJsonPath('data.2.state', 'active')
            ->assertJsonPath('data.2.times_used', 0);

        expect($this->getJson('/api/v1/coupons?filter[state]=active')->json('data.*.code'))->toBe(['ACTIVE10'])
            ->and($this->getJson('/api/v1/coupons?filter[state]=expired')->json('data.*.code'))->toBe(['OLD5'])
            ->and($this->getJson('/api/v1/coupons?filter[state]=scheduled')->json('data.*.code'))->toBe(['LATER'])
            ->and($this->getJson('/api/v1/coupons?filter[type]=fixed')->json('data.*.code'))->toBe(['OLD5'])
            ->and($this->getJson('/api/v1/coupons?search=act')->json('data.*.code'))->toBe(['ACTIVE10']);
    });

    it('is refused to Roles that do not see Coupons', function (Role $role) {
        $coupon = Coupon::factory()->create();

        $this->actingAs(userWithRole($role))->getJson('/api/v1/coupons')->assertForbidden();
        $this->getJson("/api/v1/coupons/$coupon->id")->assertForbidden();
        $this->getJson("/api/v1/coupons/$coupon->id/redemptions")->assertForbidden();
    })->with([Role::Inventory, Role::Support]);
});

describe('creating and editing a Coupon', function () {
    it('creates a Coupon with its code in capitals, eligible items and an audit entry', function () {
        $product = Product::factory()->create(['name' => 'Linen shirt']);
        $category = Category::factory()->create(['name' => 'Shoes']);

        $coupon = $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/coupons', couponPayload(['product_ids' => [$product->id], 'category_ids' => [$category->id]]))
            ->assertCreated()
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.value', '10.00')
            ->assertJsonPath('data.minimum_order_amount', '20.00')
            ->assertJsonPath('data.state', 'active')
            ->assertJsonPath('data.products', [['id' => $product->id, 'name' => 'Linen shirt']])
            ->assertJsonPath('data.categories', [['id' => $category->id, 'name' => 'Shoes']])
            ->json('data');

        expect(auditTrailOf('coupon', $coupon['id'])->sole()->after)->toMatchArray([
            'code' => 'SAVE10',
            'type' => 'percentage',
            'product_ids' => [$product->id],
            'category_ids' => [$category->id],
        ]);
    });

    it('edits a Coupon and records what changed', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);

        $this->actingAs(userWithRole(Role::Admin))
            ->putJson("/api/v1/coupons/$coupon->id", couponPayload(['type' => 'fixed', 'value' => '7.50', 'usage_limit' => null]))
            ->assertOk()
            ->assertJsonPath('data.type', 'fixed')
            ->assertJsonPath('data.value', '7.50')
            ->assertJsonPath('data.usage_limit', null);

        expect(auditTrailOf('coupon', $coupon->id)->last()?->after)->toMatchArray(['type' => 'fixed', 'value' => '7.50']);
    });

    it('refuses a taken code, a bad code, a percentage over 100, an end before the start and unknown items', function () {
        Coupon::factory()->create(['code' => 'SAVE10']);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/coupons', couponPayload([
                'value' => '150',
                'ends_on' => Carbon::yesterday()->toDateString(),
                'product_ids' => [999999],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'value', 'ends_on', 'product_ids.0']);

        $this->postJson('/api/v1/coupons', couponPayload(['code' => 'no spaces!', 'value' => '0', 'usage_limit' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Use letters, digits, - and _ only.', 'value', 'usage_limit']);
    });

    it('is refused to Roles that may not manage Coupons', function (Role $role) {
        $coupon = Coupon::factory()->create();

        $this->actingAs(userWithRole($role))->postJson('/api/v1/coupons', couponPayload())->assertForbidden();
        $this->putJson("/api/v1/coupons/$coupon->id", couponPayload())->assertForbidden();
        $this->deleteJson("/api/v1/coupons/$coupon->id")->assertForbidden();
    })->with([Role::Operations, Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('deleting a Coupon', function () {
    it('deletes an unused Coupon and records it', function () {
        $coupon = Coupon::factory()->create();

        $this->actingAs(userWithRole(Role::Sales))->deleteJson("/api/v1/coupons/$coupon->id")->assertNoContent();

        expect(Coupon::query()->exists())->toBeFalse()
            ->and(auditTrailOf('coupon', $coupon->id)->last()?->action->value)->toBe('deleted');
    });

    it('refuses to delete a Coupon an Order uses', function () {
        $coupon = Coupon::factory()->create(['code' => 'USED']);
        $variant = Variant::factory()->create(['price' => '10.00']);
        placeOrder(Warehouse::factory()->create(), [[$variant, 1]], couponCode: 'USED');

        $this->actingAs(userWithRole(Role::Sales))
            ->deleteJson("/api/v1/coupons/$coupon->id")
            ->assertConflict()
            ->assertJsonPath('code', 'in_use');
    });
});
