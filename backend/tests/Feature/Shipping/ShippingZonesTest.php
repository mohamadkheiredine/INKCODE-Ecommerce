<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;

describe('listing Shipping zones', function () {
    it('lists zones with the places they cover and their method counts, filtered by country', function () {
        $gulf = ShippingZone::factory()->covering('AE', 'SA/Riyadh')->create(['name' => 'Gulf']);
        ShippingMethod::factory()->for($gulf, 'zone')->create();
        ShippingMethod::factory()->for($gulf, 'zone')->inactive()->create();
        ShippingZone::factory()->covering('LB')->create(['name' => 'Lebanon']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/shipping-zones')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Gulf')
            ->assertJsonPath('data.0.locations', [
                ['country_code' => 'AE', 'city' => null],
                ['country_code' => 'SA', 'city' => 'Riyadh'],
            ])
            ->assertJsonPath('data.0.methods_count', 2)
            ->assertJsonPath('data.0.active_methods_count', 1)
            ->assertJsonPath('meta.total', 2);

        expect($this->getJson('/api/v1/shipping-zones?filter[country]=sa')->json('data.*.name'))->toBe(['Gulf'])
            ->and($this->getJson('/api/v1/shipping-zones?search=leb')->json('data.*.name'))->toBe(['Lebanon']);
    });

    it('is hidden from Roles that neither see Orders nor manage shipping', function () {
        // Every Role sees Orders today; a guest is the boundary that remains.
        $this->getJson('/api/v1/shipping-zones')->assertUnauthorized();
    });
});

describe('creating a Shipping zone', function () {
    it('creates a zone covering countries and cities and records it in the audit trail', function () {
        $operations = userWithRole(Role::Operations);

        $id = $this->actingAs($operations)
            ->postJson('/api/v1/shipping-zones', [
                'name' => 'Gulf',
                'locations' => [['country_code' => 'ae'], ['country_code' => 'SA', 'city' => ' Riyadh ']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gulf')
            ->assertJsonPath('data.locations', [
                ['country_code' => 'AE', 'city' => null],
                ['country_code' => 'SA', 'city' => 'Riyadh'],
            ])
            ->json('data.id');

        $entry = auditTrailOf('shipping_zone', $id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->user_id)->toBe($operations->id)
            ->and($entry->after)->toBe(['name' => 'Gulf', 'locations' => ['AE', 'SA/Riyadh']]);
    });

    it('refuses a place another zone already covers, whatever its letter case', function () {
        ShippingZone::factory()->covering('LB/Beirut')->create(['name' => 'Beirut']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/shipping-zones', [
                'name' => 'Greater Beirut',
                'locations' => [['country_code' => 'LB', 'city' => 'BEIRUT']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locations.0' => 'BEIRUT, Lebanon is already in the Shipping zone "Beirut". A place can belong to one zone only.']);
    });

    it('lets one zone cover a whole country while another covers one of its cities', function () {
        ShippingZone::factory()->covering('LB/Beirut')->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/shipping-zones', ['name' => 'Lebanon', 'locations' => [['country_code' => 'LB']]])
            ->assertCreated();
    });

    it('needs a unique name and at least one known, unrepeated place', function () {
        ShippingZone::factory()->create(['name' => 'Gulf']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/shipping-zones', ['name' => 'Gulf', 'locations' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'locations']);

        $this->postJson('/api/v1/shipping-zones', ['name' => 'Levant', 'locations' => [['country_code' => 'XX']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locations.0.country_code']);

        $this->postJson('/api/v1/shipping-zones', ['name' => 'Levant', 'locations' => [['country_code' => 'JO'], ['country_code' => 'jo']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locations.1' => 'Jordan is listed twice.']);
    });

    it('refuses Roles that may not manage shipping', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/shipping-zones', ['name' => 'Gulf', 'locations' => [['country_code' => 'AE']]])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        expect(ShippingZone::query()->count())->toBe(0);
    });
});

describe('editing a Shipping zone', function () {
    it('shows the zone with all its methods, cheapest first', function () {
        $zone = ShippingZone::factory()->covering('LB')->create();
        ShippingMethod::factory()->for($zone, 'zone')->create(['name' => 'Express', 'price' => '15.00']);
        ShippingMethod::factory()->for($zone, 'zone')->inactive()->create(['name' => 'Standard', 'price' => '7.50']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson("/api/v1/shipping-zones/{$zone->id}")
            ->assertOk()
            ->assertJsonPath('data.methods.0.name', 'Standard')
            ->assertJsonPath('data.methods.0.is_active', false)
            ->assertJsonPath('data.methods.1.price', '15.00');
    });

    it('renames it, replaces its places (keeping its own) and records what changed', function () {
        $zone = ShippingZone::factory()->covering('AE', 'SA/Riyadh')->create(['name' => 'Gulf']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/shipping-zones/{$zone->id}", [
                'name' => 'Gulf states',
                'locations' => [['country_code' => 'AE'], ['country_code' => 'KW']],
            ])
            ->assertOk()
            ->assertJsonPath('data.locations.1', ['country_code' => 'KW', 'city' => null]);

        $entry = auditTrailOf('shipping_zone', $zone->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->before)->toBe(['name' => 'Gulf', 'locations' => ['AE', 'SA/Riyadh']])
            ->and($entry->after)->toBe(['name' => 'Gulf states', 'locations' => ['AE', 'KW']]);
    });

    it('refuses Roles that may not manage shipping', function () {
        $zone = ShippingZone::factory()->covering('AE')->create(['name' => 'Gulf']);

        $this->actingAs(userWithRole(Role::Inventory))
            ->putJson("/api/v1/shipping-zones/{$zone->id}", ['name' => 'Gulf states', 'locations' => [['country_code' => 'AE']]])
            ->assertForbidden();
    });
});

describe('deleting a Shipping zone', function () {
    it('deletes the zone with its methods, auditing each', function () {
        $zone = ShippingZone::factory()->covering('AE')->create();
        $method = ShippingMethod::factory()->for($zone, 'zone')->create();

        $this->actingAs(userWithRole(Role::Admin))
            ->deleteJson("/api/v1/shipping-zones/{$zone->id}")
            ->assertNoContent();

        expect(ShippingMethod::query()->count())->toBe(0)
            ->and(auditTrailOf('shipping_zone', $zone->id)->sole()->action)->toBe(AuditAction::Deleted)
            ->and(auditTrailOf('shipping_method', $method->id)->sole()->action)->toBe(AuditAction::Deleted);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/shipping-zones', ['name' => 'Again', 'locations' => [['country_code' => 'AE']]])
            ->assertCreated();
    });

    it('refuses Roles that may not manage shipping', function () {
        $zone = ShippingZone::factory()->create();

        $this->actingAs(userWithRole(Role::Viewer))
            ->deleteJson("/api/v1/shipping-zones/{$zone->id}")
            ->assertForbidden();
    });
});
