<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\QueryException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addressPayload(array $overrides = []): array
{
    return [
        'label' => 'Home',
        'recipient_name' => 'Rana Haddad',
        'phone' => '+961 3 111 222',
        'country_code' => 'lb',
        'city' => 'Beirut',
        'line1' => 'Hamra Street, Building 12',
        'line2' => 'Floor 3',
        'postal_code' => '1103',
        ...$overrides,
    ];
}

/**
 * Which of the Customer's addresses (by label) are the default billing and shipping ones.
 *
 * @return array{billing: list<string>, shipping: list<string>}
 */
function defaultsOf(Customer $customer): array
{
    $addresses = $customer->addresses()->get();

    return [
        'billing' => $addresses->where('is_default_billing', true)->pluck('label')->values()->all(),
        'shipping' => $addresses->where('is_default_shipping', true)->pluck('label')->values()->all(),
    ];
}

describe('adding an address', function () {
    it('makes a Customer\'s first address the default for billing and shipping, and records it', function () {
        $customer = Customer::factory()->create(['name' => 'Rana Haddad']);
        $support = userWithRole(Role::Support);

        $id = $this->actingAs($support)
            ->postJson("/api/v1/customers/{$customer->id}/addresses", addressPayload())
            ->assertCreated()
            ->assertJsonPath('data.country_code', 'LB')
            ->assertJsonPath('data.is_default_billing', true)
            ->assertJsonPath('data.is_default_shipping', true)
            ->json('data.id');

        $entry = auditTrailOf('customer_address', $id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->user_id)->toBe($support->id)
            ->and($entry->record_label)->toBe('Home · Rana Haddad')
            ->and($entry->after)->toMatchArray(['city' => 'Beirut', 'is_default_billing' => true]);
    });

    it('keeps the current defaults unless the new address is asked to take one over', function () {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->defaultForBoth()->for($customer)->create(['label' => 'Home']);

        $this->actingAs(userWithRole(Role::Sales));
        $this->postJson("/api/v1/customers/{$customer->id}/addresses", addressPayload(['label' => 'Office']))->assertCreated();
        expect(defaultsOf($customer))->toBe(['billing' => ['Home'], 'shipping' => ['Home']]);

        $this->postJson("/api/v1/customers/{$customer->id}/addresses", addressPayload(['label' => 'Depot', 'is_default_shipping' => true]))
            ->assertCreated();
        expect(defaultsOf($customer))->toBe(['billing' => ['Home'], 'shipping' => ['Depot']]);
    });

    it('validates the address', function () {
        $customer = Customer::factory()->create();

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/customers/{$customer->id}/addresses", addressPayload([
                'recipient_name' => '',
                'country_code' => 'XX',
                'city' => '',
                'line1' => '',
                'phone' => 'not a phone',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_name', 'country_code', 'city', 'line1', 'phone']);
    });

    it('refuses an address from a Role that may only look', function () {
        $customer = Customer::factory()->create();

        $this->actingAs(userWithRole(Role::Finance))
            ->postJson("/api/v1/customers/{$customer->id}/addresses", addressPayload())
            ->assertForbidden();
    });
});

describe('changing an address', function () {
    it('moves a default from one address to another and records both changes', function () {
        $customer = Customer::factory()->create();
        $home = CustomerAddress::factory()->defaultForBoth()->for($customer)->create(['label' => 'Home']);
        $office = CustomerAddress::factory()->for($customer)->create(['label' => 'Office']);

        $this->actingAs(userWithRole(Role::Support))
            ->putJson("/api/v1/customers/{$customer->id}/addresses/{$office->id}", addressPayload(['label' => 'Office', 'is_default_billing' => true]))
            ->assertOk()
            ->assertJsonPath('data.is_default_billing', true)
            ->assertJsonPath('data.is_default_shipping', false);

        expect(defaultsOf($customer))->toBe(['billing' => ['Office'], 'shipping' => ['Home']])
            ->and(auditTrailOf('customer_address', $home->id)->sole()->after)->toBe(['is_default_billing' => false]);
    });

    it('refuses to leave a Customer without a default address', function () {
        $customer = Customer::factory()->create();
        $home = CustomerAddress::factory()->defaultForBoth()->for($customer)->create(['label' => 'Home']);

        $this->actingAs(userWithRole(Role::Support))
            ->putJson("/api/v1/customers/{$customer->id}/addresses/{$home->id}", addressPayload(['is_default_shipping' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_default_shipping']);
    });

    it('answers 404 for another Customer\'s address', function () {
        $customer = Customer::factory()->create();
        $someoneElses = CustomerAddress::factory()->defaultForBoth()->create();

        $this->actingAs(userWithRole(Role::Admin))
            ->putJson("/api/v1/customers/{$customer->id}/addresses/{$someoneElses->id}", addressPayload())
            ->assertNotFound();
    });
});

describe('removing an address', function () {
    it('hands the defaults of a removed address to the oldest remaining one', function () {
        $customer = Customer::factory()->create();
        $home = CustomerAddress::factory()->defaultForBoth()->for($customer)->create(['label' => 'Home']);
        CustomerAddress::factory()->for($customer)->create(['label' => 'Office']);
        CustomerAddress::factory()->for($customer)->create(['label' => 'Depot']);

        $this->actingAs(userWithRole(Role::Sales))
            ->deleteJson("/api/v1/customers/{$customer->id}/addresses/{$home->id}")
            ->assertNoContent();

        expect(defaultsOf($customer))->toBe(['billing' => ['Office'], 'shipping' => ['Office']])
            ->and(auditTrailOf('customer_address', $home->id)->sole()->action)->toBe(AuditAction::Deleted);
    });

    it('refuses a removal from a Role that may only look', function () {
        $address = CustomerAddress::factory()->defaultForBoth()->create();

        $this->actingAs(userWithRole(Role::Viewer))
            ->deleteJson("/api/v1/customers/{$address->customer_id}/addresses/{$address->id}")
            ->assertForbidden();
    });
});

it('lets the database itself refuse a second default billing address', function () {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->defaultForBoth()->for($customer)->create();

    expect(fn () => CustomerAddress::factory()->for($customer)->create(['is_default_billing' => true]))
        ->toThrow(QueryException::class);
});
