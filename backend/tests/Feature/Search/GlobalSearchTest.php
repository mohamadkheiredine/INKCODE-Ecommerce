<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Warehouse;

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create();
    $this->rana = Customer::factory()->create(['name' => 'Rana Haddad', 'email' => 'rana@example.com', 'phone' => '+961 3 111 222']);
    $this->variant = Variant::factory()->for(Product::factory()->state(['name' => 'Linen shirt']))->create(['sku' => 'LIN-RED-M']);
    receiveStock($this->warehouse, [[$this->variant, 10, '5.00']]);
    $this->order = placeOrder($this->warehouse, [[$this->variant, 1]], $this->rana);
});

describe('global search', function () {
    it('finds Orders by number and Customer, Products by name and SKU, Customers by name, email and phone', function () {
        $this->actingAs(userWithRole(Role::Admin));
        $search = fn (string $q): array => $this->getJson('/api/v1/search?q='.urlencode($q))->assertOk()->json('data');

        expect(array_column($search($this->order->number)['orders'], 'id'))->toBe([$this->order->id])
            ->and($search('haddad')['orders'][0])->toMatchArray([
                'number' => $this->order->number,
                'customer_name' => 'Rana Haddad',
                'status' => $this->order->status->value,
                'total' => $this->order->total,
            ])
            ->and($search('linen')['products'][0])->toMatchArray(['id' => $this->variant->product_id, 'name' => 'Linen shirt', 'sku' => 'LIN-RED-M'])
            ->and($search('red-m')['products'][0]['name'])->toBe('Linen shirt')
            ->and($search('haddad')['customers'])->toBe([['id' => $this->rana->id, 'name' => 'Rana Haddad', 'email' => 'rana@example.com', 'phone' => '+961 3 111 222']])
            ->and($search('example.com')['customers'][0]['name'])->toBe('Rana Haddad')
            ->and($search('111 222')['customers'][0]['name'])->toBe('Rana Haddad')
            ->and($search('nothing-like-this'))->toBe(['orders' => [], 'products' => [], 'customers' => []]);
    });

    it('returns at most five matches per group', function () {
        Customer::factory()->count(7)->sequence(fn ($sequence) => ['name' => "Haddad {$sequence->index}"])->create();

        $this->actingAs(userWithRole(Role::Admin))
            ->getJson('/api/v1/search?q=haddad')
            ->assertOk()
            ->assertJsonCount(5, 'data.customers');
    });

    it('treats LIKE wildcards in the search as plain characters', function () {
        $this->actingAs(userWithRole(Role::Admin))
            ->getJson('/api/v1/search?q=%25%25')
            ->assertOk()
            ->assertJsonPath('data.customers', [])
            ->assertJsonPath('data.products', []);
    });

    it('leaves out the groups a Role may not list', function () {
        $this->actingAs(userWithRole(Role::Inventory))
            ->getJson('/api/v1/search?q=haddad')
            ->assertOk()
            ->assertJsonMissingPath('data.customers')
            ->assertJsonPath('data.orders.0.id', $this->order->id)
            ->assertJsonPath('data.products', []);
    });

    it('gives every other Role all three groups', function (Role $role) {
        expect(array_keys($this->actingAs(userWithRole($role))->getJson('/api/v1/search?q=haddad')->assertOk()->json('data')))
            ->toBe(['orders', 'products', 'customers']);
    })->with(array_values(array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Inventory)));

    it('validates the search', function (string $query) {
        $this->actingAs(userWithRole(Role::Admin))
            ->getJson("/api/v1/search$query")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    })->with(['missing' => '', 'too short' => '?q=a', 'too long' => '?q='.str_repeat('a', 101)]);

    it('refuses a visitor who is not signed in', function () {
        $this->getJson('/api/v1/search?q=haddad')->assertUnauthorized();
    });
});
