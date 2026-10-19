<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Carrier;

const ARAMEX_TRACKING = 'https://www.aramex.com/track/results?ShipmentNumber={tracking_number}';

describe('listing Carriers', function () {
    it('lists Carriers, searchable and filtered by whether they have a tracking link', function () {
        Carrier::factory()->create(['name' => 'Aramex', 'tracking_url_template' => ARAMEX_TRACKING]);
        Carrier::factory()->create(['name' => 'Local courier']);

        $this->actingAs(userWithRole(Role::Inventory))
            ->getJson('/api/v1/carriers')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Aramex')
            ->assertJsonPath('data.0.tracking_url_template', ARAMEX_TRACKING)
            ->assertJsonPath('meta.total', 2);

        expect($this->getJson('/api/v1/carriers?filter[tracking]=without')->json('data.*.name'))->toBe(['Local courier'])
            ->and($this->getJson('/api/v1/carriers?search=ram')->json('data.*.name'))->toBe(['Aramex']);
    });
});

describe('creating and editing a Carrier', function () {
    it('creates a Carrier with a tracking link template and records it', function () {
        $id = $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/carriers', ['name' => 'Aramex', 'tracking_url_template' => ARAMEX_TRACKING])
            ->assertCreated()
            ->assertJsonPath('data.tracking_url_template', ARAMEX_TRACKING)
            ->json('data.id');

        expect(auditTrailOf('carrier', $id)->sole()->after)->toBe(['name' => 'Aramex', 'tracking_url_template' => ARAMEX_TRACKING])
            ->and(Carrier::query()->findOrFail($id)->trackingUrl('AB 12/3'))
            ->toBe('https://www.aramex.com/track/results?ShipmentNumber=AB%2012%2F3');
    });

    it('creates a Carrier without a tracking link', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/carriers', ['name' => 'Local courier', 'tracking_url_template' => ''])
            ->assertCreated()
            ->assertJsonPath('data.tracking_url_template', null);
    });

    it('refuses a duplicate name and a tracking link without the placeholder or a web address', function (string $template) {
        Carrier::factory()->create(['name' => 'DHL']);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/carriers', ['name' => 'DHL', 'tracking_url_template' => $template])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name has already been taken.',
                'tracking_url_template' => 'Enter a web address (http or https) that contains {tracking_number} where the tracking number goes.',
            ]);
    })->with([
        'no placeholder' => 'https://www.dhl.com/track',
        'not a web address' => 'javascript:alert({tracking_number})',
    ]);

    it('saves new values and records what changed', function () {
        $carrier = Carrier::factory()->create(['name' => 'Aramex']);

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/carriers/{$carrier->id}", ['name' => 'Aramex', 'tracking_url_template' => ARAMEX_TRACKING])
            ->assertOk();

        expect(auditTrailOf('carrier', $carrier->id)->sole()->after)->toBe(['tracking_url_template' => ARAMEX_TRACKING]);
    });

    it('refuses Roles that may not manage shipping, even those that ship Orders', function () {
        $carrier = Carrier::factory()->create(['name' => 'Aramex']);

        $this->actingAs(userWithRole(Role::Inventory))
            ->postJson('/api/v1/carriers', ['name' => 'DHL'])
            ->assertForbidden();
        $this->putJson("/api/v1/carriers/{$carrier->id}", ['name' => 'DHL'])->assertForbidden();
    });
});

describe('deleting a Carrier', function () {
    it('deletes a Carrier and records it', function () {
        $carrier = Carrier::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->deleteJson("/api/v1/carriers/{$carrier->id}")
            ->assertNoContent();

        expect(auditTrailOf('carrier', $carrier->id)->sole()->action)->toBe(AuditAction::Deleted);
    });

    it('refuses Roles that may not manage shipping', function () {
        $carrier = Carrier::factory()->create();

        $this->actingAs(userWithRole(Role::Support))
            ->deleteJson("/api/v1/carriers/{$carrier->id}")
            ->assertForbidden();
    });
});
