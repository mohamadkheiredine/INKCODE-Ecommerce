<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;

/*
 * Demo scenario 2, run for real: `demo:race` starts separate PHP processes that confirm
 * Orders for the last units at the same moment, against committed rows.
 */

afterEach(function () {
    $this->truncateDatabaseTables();
});

it('confirms exactly the Available quantity when 20 Orders race for 5 units', function () {
    Warehouse::factory()->create();
    Category::factory()->create();
    CustomerAddress::factory()->defaultForBoth()->create(['country_code' => 'LB', 'city' => 'Beirut']);
    ShippingMethod::factory()->create(['shipping_zone_id' => ShippingZone::factory()->covering('LB')->create()->id]);

    $this->artisan('demo:race', ['--workers' => 20, '--available' => 5])
        ->expectsOutputToContain('No overselling: exactly 5 of 20 confirmations succeeded.')
        ->assertSuccessful();

    $balance = StockBalance::query()->sole();
    expect(Order::query()->where('status', OrderStatus::Confirmed)->count())->toBe(5)
        ->and(Order::query()->where('status', OrderStatus::Pending)->count())->toBe(15)
        ->and([$balance->on_hand, $balance->reserved])->toBe([5, 5])
        ->and(StockMovement::query()->sum('reserved_delta'))->toEqual(5)
        ->and(StockMovement::query()->where('type', 'reservation')->count())->toBe(5);
});

it('explains what is missing when the store is not seeded', function () {
    $this->artisan('demo:race')
        ->expectsOutputToContain('Seed the store first')
        ->assertFailed();
});
