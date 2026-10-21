<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;

/**
 * Places an Order and moves it straight to a status and day, optionally with set amounts
 * (the way the worked example states them).
 *
 * @param  array<string, string>  $amounts
 */
function orderOn(string $placedAt, OrderStatus $status, array $amounts = []): Order
{
    $order = placeOrder(test()->warehouse, [[test()->shirt, 1]]);
    $order->forceFill([...$amounts, 'status' => $status, 'placed_at' => $placedAt])->saveQuietly();

    return $order;
}

function dashboard(string $from, string $to): TestResponse
{
    return test()->getJson("/api/v1/dashboard?from=$from&to=$to");
}

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00:00');
    $this->warehouse = Warehouse::factory()->create();
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
    receiveStock($this->warehouse, [[$this->shirt, 500, '5.00']]);
    $this->actingAs(userWithRole(Role::Admin));
});

describe('KPIs', function () {
    it('follows the worked example: Revenue $99.50 and Net sales $70', function () {
        orderOn('2026-10-15 09:00:00', OrderStatus::Delivered, [
            'items_total' => '100.00', 'discount_total' => '10.00', 'vat_total' => '4.50',
            'shipping_total' => '5.00', 'total' => '99.50', 'refunded_amount' => '20.00',
        ]);

        dashboard('2026-10-15', '2026-10-15')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue.value', '99.50')
            ->assertJsonPath('data.kpis.net_sales.value', '70.00')
            ->assertJsonPath('data.kpis.orders.value', 1)
            ->assertJsonPath('data.kpis.average_order_value.value', '99.50')
            ->assertJsonPath('data.kpis.customers.value', 1)
            ->assertJsonPath('data.kpis.pending_fulfillment.value', 0)
            ->assertJsonMissingPath('data.kpis.conversion_rate');
    });

    it('counts only Counted orders and reports Cancelled ones as cancellations', function () {
        orderOn('2026-10-14 10:00:00', OrderStatus::Confirmed, ['total' => '30.00', 'items_total' => '25.00', 'discount_total' => '0.00']);
        orderOn('2026-10-14 11:00:00', OrderStatus::Preparing, ['total' => '45.00', 'items_total' => '40.00', 'discount_total' => '0.00']);
        orderOn('2026-10-14 12:00:00', OrderStatus::Pending, ['total' => '500.00']);
        orderOn('2026-10-14 13:00:00', OrderStatus::Cancelled, ['total' => '700.00']);

        dashboard('2026-10-14', '2026-10-14')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue.value', '75.00')
            ->assertJsonPath('data.kpis.net_sales.value', '65.00')
            ->assertJsonPath('data.kpis.orders.value', 2)
            ->assertJsonPath('data.kpis.average_order_value.value', '37.50')
            ->assertJsonPath('data.kpis.pending_fulfillment.value', 2)
            ->assertJsonPath('data.kpis.cancellations.value', 1)
            ->assertJsonPath('data.status_breakdown', [
                ['status' => 'confirmed', 'count' => 1],
                ['status' => 'preparing', 'count' => 1],
                ['status' => 'ready_for_shipping', 'count' => 0],
                ['status' => 'shipped', 'count' => 0],
                ['status' => 'delivered', 'count' => 0],
            ])
            ->assertJsonPath('data.recent_orders.0.status', 'preparing')
            ->assertJsonCount(2, 'data.recent_orders');
    });

    it('dates Orders by the day they were placed, both days of the range included', function () {
        orderOn('2026-10-09 23:59:59', OrderStatus::Confirmed, ['total' => '1.00']);
        orderOn('2026-10-10 00:00:00', OrderStatus::Confirmed, ['total' => '10.00']);
        orderOn('2026-10-12 23:59:59', OrderStatus::Confirmed, ['total' => '20.00']);
        orderOn('2026-10-13 00:00:00', OrderStatus::Confirmed, ['total' => '100.00']);

        dashboard('2026-10-10', '2026-10-12')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue.value', '30.00')
            ->assertJsonPath('data.revenue_by_day', [
                ['date' => '2026-10-10', 'revenue' => '10.00', 'previous_revenue' => '0.00'],
                ['date' => '2026-10-11', 'revenue' => '0.00', 'previous_revenue' => '0.00'],
                ['date' => '2026-10-12', 'revenue' => '20.00', 'previous_revenue' => '1.00'],
            ]);
    });
});

describe('previous-period comparison', function () {
    it('compares with the period of the same length just before', function () {
        orderOn('2026-10-08 10:00:00', OrderStatus::Delivered, ['total' => '50.00']);
        orderOn('2026-10-10 10:00:00', OrderStatus::Delivered, ['total' => '60.00']);
        orderOn('2026-10-14 10:00:00', OrderStatus::Delivered, ['total' => '99.00']);

        dashboard('2026-10-11', '2026-10-15')
            ->assertOk()
            ->assertJsonPath('data.range', [
                'from' => '2026-10-11', 'to' => '2026-10-15',
                'previous_from' => '2026-10-06', 'previous_to' => '2026-10-10',
            ])
            ->assertJsonPath('data.kpis.revenue.value', '99.00')
            ->assertJsonPath('data.kpis.revenue.previous', '110.00')
            ->assertJsonPath('data.kpis.revenue.change', -10)
            ->assertJsonPath('data.kpis.orders.change', -50)
            ->assertJsonPath('data.revenue_by_day.2.previous_revenue', '50.00')
            ->assertJsonPath('data.revenue_by_day.3.revenue', '99.00');
    });

    it('handles a zero baseline: no change when both are zero, null when only the period has some', function () {
        orderOn('2026-10-15 10:00:00', OrderStatus::Confirmed, ['total' => '40.00']);

        dashboard('2026-10-15', '2026-10-15')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue.previous', '0.00')
            ->assertJsonPath('data.kpis.revenue.change', null)
            ->assertJsonPath('data.kpis.cancellations.change', 0)
            ->assertJsonPath('data.kpis.low_stock.previous', null);
    });
});

describe('reconciliation', function () {
    it('shows Revenue equal to the sum of the Counted orders\' totals', function () {
        foreach ([OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Pending, OrderStatus::Cancelled] as $index => $status) {
            $order = placeOrder($this->warehouse, [[$this->shirt, $index + 1]]);
            $order->forceFill(['status' => $status, 'placed_at' => "2026-10-1{$index} 08:00:00"])->saveQuietly();
        }

        $expected = Order::query()->counted()->whereBetween('placed_at', ['2026-10-10 00:00:00', '2026-10-15 23:59:59'])->get()
            ->reduce(fn (string $sum, Order $order): string => bcadd($sum, $order->total, 2), '0.00');
        $response = dashboard('2026-10-10', '2026-10-15')->assertOk();

        expect($response->json('data.kpis.revenue.value'))->toBe($expected)
            ->and(bccomp($expected, '0', 2))->toBe(1)
            ->and(collect($response->json('data.revenue_by_day'))->reduce(fn (string $sum, array $day): string => bcadd($sum, $day['revenue'], 2), '0.00'))->toBe($expected)
            ->and($response->json('data.top_products.0'))->toMatchArray(['product_id' => $this->shirt->product_id, 'units' => 6]);
    });
});

describe('lists and alerts', function () {
    it('ranks top Products and raises alerts for what needs attention now', function () {
        $hoodie = Variant::factory()->create(['sku' => 'HOOD-M', 'price' => '60.00']);
        receiveStock($this->warehouse, [[$hoodie, 50, '20.00']]);
        $hoodieOrder = placeOrder($this->warehouse, [[$hoodie, 1]]);
        $hoodieOrder->forceFill(['status' => OrderStatus::Confirmed, 'placed_at' => '2026-10-15 08:00:00'])->saveQuietly();
        orderOn('2026-10-15 09:00:00', OrderStatus::Confirmed);
        orderOn('2026-10-15 10:00:00', OrderStatus::Pending);

        dashboard('2026-10-15', '2026-10-15')
            ->assertOk()
            ->assertJsonCount(2, 'data.top_products')
            ->assertJsonPath('data.top_products.0.product_id', $hoodie->product_id)
            ->assertJsonPath('data.top_products.0.units', 1)
            ->assertJsonPath('data.top_products.1.product_id', $this->shirt->product_id)
            ->assertJsonPath('data.recent_orders.0.number', Order::query()->counted()->latest('placed_at')->firstOrFail()->number)
            ->assertJsonFragment(['type' => 'orders_awaiting_confirmation', 'count' => 1]);
    });
});

describe('date range', function () {
    it('defaults to the last 30 days up to today', function () {
        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.range.from', '2026-09-16')
            ->assertJsonPath('data.range.to', '2026-10-15')
            ->assertJsonCount(30, 'data.revenue_by_day');
    });

    it('rejects an invalid range with 422', function (array $query, string $field) {
        $this->getJson('/api/v1/dashboard?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'end before start' => [['from' => '2026-10-15', 'to' => '2026-10-14'], 'to'],
        'not a date' => [['from' => '15/10/2026', 'to' => '2026-10-15'], 'from'],
        'only one end' => [['from' => '2026-10-01'], 'to'],
        'longer than a year' => [['from' => '2025-01-01', 'to' => '2026-10-15'], 'to'],
    ]);
});

describe('access', function () {
    it('lets every Role with dashboard.view see it', function (Role $role) {
        $this->actingAs(userWithRole($role));

        $this->getJson('/api/v1/dashboard')->assertOk();
    })->with(Role::cases());

    it('refuses a User without dashboard.view with 403 and a guest with 401', function () {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/dashboard')->assertForbidden();

        asBrowser()->getJson('/api/v1/dashboard')->assertUnauthorized();
    });
});
