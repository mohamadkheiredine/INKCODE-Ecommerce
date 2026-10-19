<?php

use App\Enums\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;

/**
 * A zone for all of Lebanon with Standard and Express, and a Beirut zone with its own
 * cheaper Standard. Beirut is inside Lebanon, so the city zone must win there.
 */
function lebanonAndBeirutZones(): array
{
    $lebanon = ShippingZone::factory()->covering('LB')->create(['name' => 'Lebanon']);
    ShippingMethod::factory()->for($lebanon, 'zone')->create(['name' => 'Standard', 'price' => '7.50', 'min_delivery_days' => 2, 'max_delivery_days' => 4]);
    ShippingMethod::factory()->for($lebanon, 'zone')->create(['name' => 'Express', 'price' => '15.00', 'min_delivery_days' => 1, 'max_delivery_days' => 1]);
    ShippingMethod::factory()->for($lebanon, 'zone')->inactive()->create(['name' => 'Overnight', 'price' => '30.00']);

    $beirut = ShippingZone::factory()->covering('LB/Beirut')->create(['name' => 'Beirut']);
    ShippingMethod::factory()->for($beirut, 'zone')->create(['name' => 'Same-day', 'price' => '4.00', 'min_delivery_days' => 0, 'max_delivery_days' => 0]);

    return [$lebanon, $beirut];
}

describe('quoting shipping for an address', function () {
    it('uses the zone that names the city before the zone that covers its country', function () {
        [, $beirut] = lebanonAndBeirutZones();

        $this->actingAs(userWithRole(Role::Sales))
            ->getJson('/api/v1/shipping/quote?country_code=LB&city=beirut')
            ->assertOk()
            ->assertJsonPath('data.zone.id', $beirut->id)
            ->assertJsonPath('data.zone.name', 'Beirut')
            ->assertJsonPath('data.message', null)
            ->assertJsonPath('data.methods.0.name', 'Same-day')
            ->assertJsonPath('data.methods.0.price', '4.00')
            ->assertJsonCount(1, 'data.methods');
    });

    it('falls back to the zone covering the whole country, offering only active methods, cheapest first', function () {
        [$lebanon] = lebanonAndBeirutZones();

        $response = $this->actingAs(userWithRole(Role::Sales))
            ->getJson('/api/v1/shipping/quote?country_code=lb&city=Tripoli')
            ->assertOk()
            ->assertJsonPath('data.zone.id', $lebanon->id);

        expect($response->json('data.methods'))->toBe([
            ['id' => ShippingMethod::query()->where('name', 'Standard')->value('id'), 'name' => 'Standard', 'price' => '7.50', 'min_delivery_days' => 2, 'max_delivery_days' => 4],
            ['id' => ShippingMethod::query()->where('name', 'Express')->value('id'), 'name' => 'Express', 'price' => '15.00', 'min_delivery_days' => 1, 'max_delivery_days' => 1],
        ]);
    });

    it('offers no methods, with a clear message, for an address no zone covers', function () {
        lebanonAndBeirutZones();

        $this->actingAs(userWithRole(Role::Sales))
            ->getJson('/api/v1/shipping/quote?country_code=FR&city=Paris')
            ->assertOk()
            ->assertJsonPath('data.zone', null)
            ->assertJsonPath('data.methods', [])
            ->assertJsonPath('data.message', 'No Shipping zone covers Paris, France. Add the city or its country to a Shipping zone first.');
    });

    it('needs a known country and a city', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->getJson('/api/v1/shipping/quote?country_code=XX')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code', 'city']);
    });

    it('is only for Roles that create Orders or manage shipping', function () {
        $this->actingAs(userWithRole(Role::Finance))
            ->getJson('/api/v1/shipping/quote?country_code=LB&city=Beirut')
            ->assertForbidden();
    });
});
