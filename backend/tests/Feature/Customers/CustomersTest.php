<?php

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Tag;

describe('listing Customers', function () {
    it('lists Customers a page at a time with their Tags', function () {
        Customer::factory()->withTags('wholesale')->create(['name' => 'Rana Haddad', 'created_at' => now()->subDay()]);
        Customer::factory()->create(['name' => 'Omar Khalil']);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/customers?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Omar Khalil')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/customers?page=2&per_page=1')
            ->assertJsonPath('data.0.name', 'Rana Haddad')
            ->assertJsonPath('data.0.tags.0.name', 'wholesale');
    });

    it('searches by name, email or phone, filters by Tag and sorts by name', function () {
        Customer::factory()->withTags('wholesale')->create(['name' => 'Rana Haddad', 'email' => 'rana@example.com', 'phone' => '+961 3 111 222']);
        Customer::factory()->withTags('wholesale', 'vip')->create(['name' => 'Omar Khalil', 'email' => null, 'phone' => '+961 70 999 000']);
        Customer::factory()->create(['name' => 'Zeina Saab', 'email' => 'zeina@shop.test', 'phone' => null]);
        $vip = Tag::query()->where('name', 'vip')->sole();

        $this->actingAs(userWithRole(Role::Support));
        $names = fn (string $query): array => $this->getJson("/api/v1/customers?$query")->assertOk()->json('data.*.name');

        expect($names('search=haddad'))->toBe(['Rana Haddad'])
            ->and($names('search=shop.test'))->toBe(['Zeina Saab'])
            ->and($names('search=70 999'))->toBe(['Omar Khalil'])
            ->and($names("filter[tag]={$vip->id}"))->toBe(['Omar Khalil'])
            ->and($names('sort=name'))->toBe(['Omar Khalil', 'Rana Haddad', 'Zeina Saab'])
            ->and($names('sort=-name'))->toBe(['Zeina Saab', 'Rana Haddad', 'Omar Khalil']);
    });

    it('refuses to list Customers to a Role without access to them', function () {
        $this->actingAs(userWithRole(Role::Inventory))->getJson('/api/v1/customers')->assertForbidden();
    });
});

describe('creating a Customer', function () {
    it('creates a Customer with Tags made on the fly and records it in the audit trail', function () {
        Tag::query()->create(['name' => 'wholesale']);
        $sales = userWithRole(Role::Sales);

        $id = $this->actingAs($sales)
            ->postJson('/api/v1/customers', [
                'name' => '  Rana Haddad ',
                'email' => 'Rana@Example.com',
                'phone' => '+961 3 111 222',
                'tags' => ['Wholesale', 'repeat buyer', 'repeat buyer'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Rana Haddad')
            ->assertJsonPath('data.email', 'rana@example.com')
            ->assertJsonPath('data.tags.*.name', ['repeat buyer', 'wholesale'])
            ->assertJsonPath('data.addresses', [])
            ->json('data.id');

        expect(Tag::query()->pluck('name')->sort()->values()->all())->toBe(['repeat buyer', 'wholesale']);

        $entry = auditTrailOf('customer', $id)->sole();
        expect($entry->action)->toBe(AuditAction::Created)
            ->and($entry->user_id)->toBe($sales->id)
            ->and($entry->after)->toEqual([
                'name' => 'Rana Haddad',
                'email' => 'rana@example.com',
                'phone' => '+961 3 111 222',
                'tags' => ['repeat buyer', 'wholesale'],
            ]);
    });

    it('needs an email or a phone', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/customers', ['name' => 'Rana Haddad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);

        $this->postJson('/api/v1/customers', ['name' => 'Rana Haddad', 'phone' => '+961 3 111 222'])->assertCreated();
    });

    it('refuses an email another Customer has, a malformed phone and an over-long Tag', function () {
        Customer::factory()->create(['email' => 'rana@example.com']);

        $this->actingAs(userWithRole(Role::Support))
            ->postJson('/api/v1/customers', [
                'name' => 'Someone',
                'email' => 'RANA@example.com',
                'phone' => 'call me',
                'tags' => [str_repeat('x', 51)],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'tags.0']);
    });

    it('lets several Customers go without an email', function () {
        Customer::factory()->create(['email' => null, 'phone' => '+961 1 000 000']);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/customers', ['name' => 'Walk-in', 'phone' => '+961 1 000 001'])
            ->assertCreated();
    });

    it('refuses to create a Customer for a Role that may only look', function () {
        $this->actingAs(userWithRole(Role::Finance))
            ->postJson('/api/v1/customers', ['name' => 'Rana Haddad', 'phone' => '+961 3 111 222'])
            ->assertForbidden();
    });
});

describe('a Customer profile', function () {
    it('shows a Customer with Tags and addresses', function () {
        $customer = Customer::factory()->withTags('vip')->hasAddresses(1, ['label' => 'Home'])->create();

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $customer->name)
            ->assertJsonPath('data.tags.0.name', 'vip')
            ->assertJsonPath('data.addresses.0.label', 'Home');
    });

    it('answers 404 for a Customer that does not exist', function () {
        $this->actingAs(userWithRole(Role::Viewer))->getJson('/api/v1/customers/999')->assertNotFound();
    });
});

describe('updating a Customer', function () {
    it('saves new details and Tags and records only what changed', function () {
        $customer = Customer::factory()->withTags('wholesale')->create(['name' => 'Rana Haddad', 'email' => 'rana@example.com', 'phone' => null]);

        $this->actingAs(userWithRole(Role::Support))
            ->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Rana Haddad', 'email' => 'rana@example.com', 'tags' => ['vip']])
            ->assertOk()
            ->assertJsonPath('data.tags.*.name', ['vip']);

        $entry = auditTrailOf('customer', $customer->id)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->before)->toEqual(['tags' => ['wholesale']])
            ->and($entry->after)->toEqual(['tags' => ['vip']]);
    });

    it('keeps a Customer\'s own email unique against itself only', function () {
        $customer = Customer::factory()->create(['email' => 'rana@example.com']);
        Customer::factory()->create(['email' => 'omar@example.com']);

        $this->actingAs(userWithRole(Role::Sales));
        $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Rana', 'email' => 'rana@example.com'])->assertOk();
        $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Rana', 'email' => 'omar@example.com'])
            ->assertJsonValidationErrors(['email']);
    });

    it('refuses an update from a Role that may only look', function () {
        $customer = Customer::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->putJson("/api/v1/customers/{$customer->id}", ['name' => 'X', 'phone' => '+961 1 000 000'])
            ->assertForbidden();
    });
});

describe('deleting a Customer', function () {
    it('deletes a Customer without Orders along with their addresses and notes', function () {
        $customer = Customer::factory()->withTags('vip')->hasAddresses(2)->hasNotes(1)->create();

        $this->actingAs(userWithRole(Role::Sales))
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertNoContent();

        expect(Customer::query()->count())->toBe(0)
            ->and(Tag::query()->where('name', 'vip')->exists())->toBeTrue()
            ->and(auditTrailOf('customer', $customer->id)->last()?->action)->toBe(AuditAction::Deleted);
    });

    it('refuses a delete from a Role that may only look', function () {
        $customer = Customer::factory()->create();

        $this->actingAs(userWithRole(Role::Viewer))->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
    });
});

describe('Tags', function () {
    it('lists Tags for the filter and the picker, searchable by name', function () {
        Tag::query()->create(['name' => 'wholesale']);
        Tag::query()->create(['name' => 'vip']);

        $this->actingAs(userWithRole(Role::Viewer));
        expect($this->getJson('/api/v1/customer-tags')->assertOk()->json('data.*.name'))->toBe(['vip', 'wholesale'])
            ->and($this->getJson('/api/v1/customer-tags?search=whole')->json('data.*.name'))->toBe(['wholesale']);
    });

    it('refuses the Tags list to a Role without access to Customers', function () {
        $this->actingAs(userWithRole(Role::Inventory))->getJson('/api/v1/customer-tags')->assertForbidden();
    });
});
