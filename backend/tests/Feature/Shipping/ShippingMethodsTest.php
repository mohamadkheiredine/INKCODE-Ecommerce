<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;

describe('adding a Shipping method to a zone', function () {
    it('adds a flat-price method with its delivery days and records it', function () {
        $zone = ShippingZone::factory()->covering('LB')->create(['name' => 'Lebanon']);

        $id = $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/shipping-zones/{$zone->id}/methods", [
                'name' => 'Express',
                'price' => '12.5',
                'min_delivery_days' => 1,
                'max_delivery_days' => 2,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.shipping_zone_id', $zone->id)
            ->assertJsonPath('data.price', '12.50')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        expect(auditTrailOf('shipping_method', $id)->sole()->after)->toEqual([
            'shipping_zone_id' => $zone->id,
            'name' => 'Express',
            'price' => '12.50',
            'min_delivery_days' => 1,
            'max_delivery_days' => 2,
            'is_active' => true,
        ]);
    });

    it('refuses a negative or over-precise price, inverted delivery days and a name the zone already uses', function () {
        $zone = ShippingZone::factory()->create();
        ShippingMethod::factory()->for($zone, 'zone')->create(['name' => 'Standard']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/shipping-zones/{$zone->id}/methods", [
                'name' => 'Standard',
                'price' => '-1',
                'min_delivery_days' => 5,
                'max_delivery_days' => 2,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price', 'max_delivery_days']);

        $this->postJson("/api/v1/shipping-zones/{$zone->id}/methods", [
            'name' => 'Express', 'price' => '9.999', 'min_delivery_days' => 1, 'max_delivery_days' => 1, 'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['price']);
    });

    it('lets two zones each have a method of the same name', function () {
        ShippingMethod::factory()->create(['name' => 'Standard']);
        $zone = ShippingZone::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/shipping-zones/{$zone->id}/methods", [
                'name' => 'Standard', 'price' => '5', 'min_delivery_days' => 2, 'max_delivery_days' => 4, 'is_active' => true,
            ])
            ->assertCreated();
    });

    it('refuses Roles that may not manage shipping', function () {
        $zone = ShippingZone::factory()->create();

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/shipping-zones/{$zone->id}/methods", [
                'name' => 'Express', 'price' => '12.50', 'min_delivery_days' => 1, 'max_delivery_days' => 2, 'is_active' => true,
            ])
            ->assertForbidden();
    });
});

describe('editing and deleting a Shipping method', function () {
    it('deactivates a method, records what changed, and stops quoting it', function () {
        $zone = ShippingZone::factory()->covering('LB')->create();
        $method = ShippingMethod::factory()->for($zone, 'zone')->create(['name' => 'Express', 'price' => '12.50', 'min_delivery_days' => 1, 'max_delivery_days' => 2]);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/shipping-methods/{$method->id}", [
                'name' => 'Express', 'price' => '12.50', 'min_delivery_days' => 1, 'max_delivery_days' => 2, 'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $entry = auditTrailOf('shipping_method', $method->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->before)->toBe(['is_active' => true])
            ->and($entry->after)->toBe(['is_active' => false]);

        $this->getJson('/api/v1/shipping/quote?country_code=LB&city=Beirut')
            ->assertJsonPath('data.methods', [])
            ->assertJsonPath('data.message', "The Shipping zone \"{$zone->name}\" has no active Shipping methods.");
    });

    it('deletes a method and records it', function () {
        $method = ShippingMethod::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/shipping-methods/{$method->id}")
            ->assertNoContent();

        expect(ShippingMethod::query()->count())->toBe(0)
            ->and(auditTrailOf('shipping_method', $method->id)->sole()->action)->toBe(AuditAction::Deleted);
    });

    it('refuses Roles that may not manage shipping', function () {
        $method = ShippingMethod::factory()->create(['price' => '5.00']);

        $this->actingAs(userWithRole(Role::Finance))
            ->putJson("/api/v1/shipping-methods/{$method->id}", [
                'name' => 'Free', 'price' => '0', 'min_delivery_days' => 1, 'max_delivery_days' => 2, 'is_active' => true,
            ])
            ->assertForbidden();
        $this->deleteJson("/api/v1/shipping-methods/{$method->id}")->assertForbidden();

        expect($method->fresh()?->price)->toBe('5.00');
    });
});
