<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Database\Seeders\DemoCustomersSeeder;

it('seeds the demo Customers once, each with addresses having exactly one default of each kind', function () {
    seedStore(demoMode: true);
    seedStore(demoMode: true);

    $this->actingAs(userWithRole(Role::Viewer))
        ->getJson('/api/v1/customers')
        ->assertJsonPath('meta.total', DemoCustomersSeeder::CUSTOMERS);

    $withAddresses = Customer::query()->has('addresses')->withCount([
        'addresses as billing_defaults' => fn ($query) => $query->where('is_default_billing', true),
        'addresses as shipping_defaults' => fn ($query) => $query->where('is_default_shipping', true),
    ])->get();

    expect($withAddresses)->not->toBeEmpty()
        ->and($withAddresses->every(fn (Customer $customer): bool => $customer->billing_defaults === 1 && $customer->shipping_defaults === 1))->toBeTrue()
        ->and(Customer::query()->whereNull('email')->whereNull('phone')->exists())->toBeFalse()
        ->and(CustomerAddress::query()->count())->toBeGreaterThan(DemoCustomersSeeder::CUSTOMERS / 2);
});

it('seeds no Customers outside demo mode', function () {
    seedStore(demoMode: false);

    expect(Customer::query()->count())->toBe(0);
});
