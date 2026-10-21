<?php

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;

/**
 * Places an Order for a Customer and moves it straight to a status and day.
 *
 * @param  list<array{Variant, int}>  $lines
 */
function reportOrder(string $placedAt, OrderStatus $status, array $lines, ?Customer $customer = null): Order
{
    $order = placeOrder(test()->warehouse, $lines, $customer);
    $order->forceFill(['status' => $status, 'placed_at' => $placedAt])->saveQuietly();

    return $order;
}

function returnFor(Order $order, ReturnReason $reason, string $requestedAt, int $units, ?string $refund = null): OrderReturn
{
    static $sequence = 0;
    $return = OrderReturn::query()->create([
        'number' => 'RET-TEST-'.++$sequence,
        'order_id' => $order->id,
        'status' => ReturnStatus::Requested,
        'reason' => $reason,
        'refund_amount' => $refund,
        'requested_at' => $requestedAt,
    ]);
    $return->lines()->create(['order_line_id' => $order->lines()->firstOrFail()->id, 'quantity' => $units]);

    return $return;
}

function showReport(string $report, string $query = 'from=2026-10-10&to=2026-10-15'): TestResponse
{
    return test()->getJson("/api/v1/reports/$report?$query");
}

/**
 * @return array{key: string, type: string, value: string|int, previous: string|int|null, change: float|int|null}
 */
function summaryValue(TestResponse $response, string $key): array
{
    return collect($response->json('data.summary'))->firstWhere('key', $key);
}

beforeEach(function () {
    $this->travelTo('2026-10-15 12:00:00');
    $this->warehouse = Warehouse::factory()->create();
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
    $this->mug = Variant::factory()->create(['sku' => 'MUG-1', 'price' => '8.00']);
    receiveStock($this->warehouse, [[$this->shirt, 500, '5.00'], [$this->mug, 500, '2.00']]);
    $this->actingAs(userWithRole(Role::Admin));
});

describe('sales', function () {
    it('groups Counted orders by day, filling empty days, and compares with the previous period', function () {
        reportOrder('2026-10-10 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]]);
        reportOrder('2026-10-12 09:00:00', OrderStatus::Delivered, [[$this->shirt, 2]]);
        reportOrder('2026-10-12 10:00:00', OrderStatus::Pending, [[$this->shirt, 9]]);
        reportOrder('2026-10-13 10:00:00', OrderStatus::Cancelled, [[$this->shirt, 9]]);
        reportOrder('2026-10-05 09:00:00', OrderStatus::Shipped, [[$this->shirt, 1]]);

        $response = showReport('sales')->assertOk()
            ->assertJsonPath('data.report', 'sales')
            ->assertJsonPath('data.range.previous_from', '2026-10-04')
            ->assertJsonCount(6, 'data.rows')
            ->assertJsonPath('data.rows.0.period', '2026-10-10')
            ->assertJsonPath('data.rows.0.orders', 1)
            ->assertJsonPath('data.rows.1.orders', 0)
            ->assertJsonPath('data.rows.1.revenue', '0.00')
            ->assertJsonPath('data.rows.2.orders', 1);

        $revenue = summaryValue($response, 'revenue');
        expect(summaryValue($response, 'orders'))->toMatchArray(['value' => 2, 'previous' => 1, 'change' => 100])
            ->and($revenue['value'])->toBe(collect($response->json('data.rows'))->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['revenue'], 2), '0.00'));
    });

    it('groups by week from Monday and by month', function () {
        reportOrder('2026-09-30 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]]);
        reportOrder('2026-10-02 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]]);
        reportOrder('2026-10-07 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]]);

        showReport('sales', 'from=2026-09-28&to=2026-10-11&interval=week')->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.period', '2026-09-28')
            ->assertJsonPath('data.rows.0.orders', 2)
            ->assertJsonPath('data.rows.1.period', '2026-10-05')
            ->assertJsonPath('data.rows.1.orders', 1);

        showReport('sales', 'from=2026-09-28&to=2026-10-11&interval=month')->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.period', '2026-09-01')
            ->assertJsonPath('data.rows.0.orders', 1)
            ->assertJsonPath('data.rows.1.period', '2026-10-01')
            ->assertJsonPath('data.rows.1.orders', 2);
    });

    it('rejects an unknown interval, a reversed range and an unknown report', function () {
        showReport('sales', 'from=2026-10-10&to=2026-10-15&interval=year')->assertUnprocessable()->assertJsonValidationErrors('interval');
        showReport('sales', 'from=2026-10-15&to=2026-10-10')->assertUnprocessable()->assertJsonValidationErrors('to');
        showReport('profits')->assertNotFound();
    });
});

describe('products and categories', function () {
    it('ranks Products by sales or by units, from Counted orders only', function () {
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1], [$this->mug, 5]]);
        reportOrder('2026-10-12 09:00:00', OrderStatus::Pending, [[$this->mug, 50]]);

        showReport('products')->assertOk()
            ->assertJsonPath('data.rows.0.product', $this->mug->product->name)
            ->assertJsonPath('data.rows.0.units', 5);
        showReport('products', 'from=2026-10-10&to=2026-10-15&rank=sales')->assertOk()
            ->assertJsonPath('data.rows.0.product', $this->mug->product->name)
            ->assertJsonPath('data.rows.0.sales', Order::query()->counted()->firstOrFail()->lines()->where('variant_id', $this->mug->id)->value('line_total'));

        $byUnits = showReport('products', 'from=2026-10-10&to=2026-10-15&rank=units')->assertOk();
        expect(summaryValue($byUnits, 'units')['value'])->toBe(6)
            ->and(summaryValue($byUnits, 'products')['value'])->toBe(2);
    });

    it('adds up sales per Category with each share', function () {
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1], [$this->mug, 5]]);

        $response = showReport('categories')->assertOk()->assertJsonCount(2, 'data.rows');
        $rows = collect($response->json('data.rows'));

        expect($rows->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['sales'], 2), '0.00'))->toBe(summaryValue($response, 'sales')['value'])
            ->and($rows->sum(fn (array $row): float => (float) $row['share']))->toEqualWithDelta(100, 0.1);
    });
});

describe('customers', function () {
    it('lists top spenders and tells New Customers from Returning ones', function () {
        $loyal = Customer::factory()->create(['name' => 'Loyal Lina']);
        reportOrder('2026-09-01 09:00:00', OrderStatus::Delivered, [[$this->shirt, 1]], $loyal);
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]], $loyal);
        $big = Customer::factory()->create(['name' => 'Big Basma']);
        reportOrder('2026-10-12 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 10]], $big);

        $response = showReport('customers')->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.customer', 'Big Basma')
            ->assertJsonPath('data.rows.0.type', 'new')
            ->assertJsonPath('data.rows.1.customer', 'Loyal Lina')
            ->assertJsonPath('data.rows.1.type', 'returning');

        expect(summaryValue($response, 'new_customers')['value'])->toBe(1)
            ->and(summaryValue($response, 'returning_customers')['value'])->toBe(1);
    });
});

describe('returns', function () {
    it('counts Returns by reason, every reason listed, with the return rate', function () {
        $first = reportOrder('2026-10-11 09:00:00', OrderStatus::Delivered, [[$this->shirt, 3]]);
        $second = reportOrder('2026-10-11 10:00:00', OrderStatus::Delivered, [[$this->shirt, 2]]);
        reportOrder('2026-10-12 10:00:00', OrderStatus::Delivered, [[$this->shirt, 1]]);
        reportOrder('2026-10-12 11:00:00', OrderStatus::Delivered, [[$this->shirt, 1]]);
        returnFor($first, ReturnReason::Damaged, '2026-10-13 09:00:00', 2, '40.00');
        returnFor($second, ReturnReason::Damaged, '2026-10-14 09:00:00', 1);

        $response = showReport('returns')->assertOk()
            ->assertJsonCount(count(ReturnReason::cases()), 'data.rows')
            ->assertJsonPath('data.rows.0', ['reason' => 'damaged', 'returns' => 2, 'units' => 3, 'refunded' => '40.00', 'share' => '100.0'])
            ->assertJsonPath('data.rows.1.returns', 0);

        expect(summaryValue($response, 'return_rate')['value'])->toBe('50.0')
            ->and(summaryValue($response, 'returns')['value'])->toBe(2);
    });
});

describe('inventory', function () {
    it('shows stock per Variant, its Stock value and low stock, with units received in the range', function () {
        $empty = Variant::factory()->create(['sku' => 'AAA-EMPTY']);
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 4]]);

        $response = showReport('inventory')->assertOk()
            ->assertJsonPath('data.rows.0.sku', $empty->sku)
            ->assertJsonPath('data.rows.0.low_stock', 1);
        $shirt = collect($response->json('data.rows'))->firstWhere('sku', 'TEE-RED-M');

        expect($shirt)->toMatchArray(['on_hand' => 500, 'reserved' => 0, 'available' => 500, 'low_stock' => 0, 'average_cost' => '5.00', 'stock_value' => '2500.00'])
            ->and(summaryValue($response, 'stock_value'))->toMatchArray(['value' => '3500.00', 'previous' => null, 'change' => null])
            ->and(summaryValue($response, 'units_received')['value'])->toBe(1000);
    });
});

describe('reconciliation with the dashboard', function () {
    it('gives the same Revenue, Net sales, Orders, Customers, Returns and low stock as the dashboard', function () {
        foreach ([OrderStatus::Confirmed, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Pending, OrderStatus::Cancelled] as $index => $status) {
            $order = reportOrder("2026-10-1{$index} 08:00:00", $status, [[$this->shirt, $index + 1], [$this->mug, 1]]);
        }
        returnFor($order, ReturnReason::Other, '2026-10-12 09:00:00', 1);
        Variant::factory()->create();

        $dashboard = $this->getJson('/api/v1/dashboard?from=2026-10-10&to=2026-10-15')->assertOk();
        $sales = showReport('sales')->assertOk();
        $customers = showReport('customers')->assertOk();
        $returns = showReport('returns')->assertOk();
        $inventory = showReport('inventory')->assertOk();
        $products = showReport('products')->assertOk();

        expect(summaryValue($sales, 'revenue')['value'])->toBe($dashboard->json('data.kpis.revenue.value'))
            ->and(summaryValue($sales, 'net_sales')['value'])->toBe($dashboard->json('data.kpis.net_sales.value'))
            ->and(summaryValue($sales, 'orders')['value'])->toBe($dashboard->json('data.kpis.orders.value'))
            ->and(summaryValue($sales, 'average_order_value')['value'])->toBe($dashboard->json('data.kpis.average_order_value.value'))
            ->and(summaryValue($customers, 'customers')['value'])->toBe($dashboard->json('data.kpis.customers.value'))
            ->and(summaryValue($customers, 'revenue')['value'])->toBe($dashboard->json('data.kpis.revenue.value'))
            ->and(summaryValue($returns, 'returns')['value'])->toBe($dashboard->json('data.kpis.returns.value'))
            ->and(summaryValue($inventory, 'low_stock')['value'])->toBe($dashboard->json('data.kpis.low_stock.value'))
            ->and($products->json('data.rows.0.sales'))->toBe($dashboard->json('data.top_products.0.sales'));
    });
});

describe('exports', function () {
    it('streams the screen\'s rows as CSV, named after the report and its dates', function () {
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]]);
        $screen = showReport('sales')->json('data.rows');

        $response = $this->get('/api/v1/reports/sales/export?from=2026-10-10&to=2026-10-15&format=csv')->assertOk();
        $lines = array_map(str_getcsv(...), explode("\n", trim(ltrim($response->streamedContent(), "\u{FEFF}"))));

        expect($response->headers->get('Content-Disposition'))->toContain('sales-report-2026-10-10-to-2026-10-15.csv')
            ->and($lines[0][0])->toBe('Period')
            ->and(count($lines))->toBe(count($screen) + 1)
            ->and($lines[2])->toBe(array_map(strval(...), array_values($screen[1])));
    });

    it('translates headers and stored values, and keeps text from running as a formula', function () {
        $customer = Customer::factory()->create(['name' => '=HYPERLINK("x")']);
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 1]], $customer);

        $content = $this->withHeader('Accept-Language', 'ar')
            ->get('/api/v1/reports/customers/export?from=2026-10-10&to=2026-10-15&format=csv')
            ->assertOk()
            ->streamedContent();

        expect($content)->toContain('العميل')->toContain('جديد')->toContain('"\'=HYPERLINK(""x"")"');
    });

    it('writes an Excel workbook whose numbers are real numbers', function () {
        reportOrder('2026-10-11 09:00:00', OrderStatus::Confirmed, [[$this->shirt, 2]]);

        $response = $this->get('/api/v1/reports/products/export?from=2026-10-10&to=2026-10-15&format=xlsx')->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($file);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        expect($response->headers->get('Content-Disposition'))->toContain('products-report-2026-10-10-to-2026-10-15.xlsx')
            ->and($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->and($sheet)->toContain('<t xml:space="preserve">Units</t>')
            ->and($sheet)->toContain('<c r="C2" s="0"><v>2</v></c>')
            ->and($sheet)->toContain('<c r="A2" s="0" t="inlineStr">');
    });

    it('needs a format of csv or xlsx', function () {
        $this->getJson('/api/v1/reports/sales/export?format=pdf')->assertUnprocessable()->assertJsonValidationErrors('format');
    });
});

describe('access', function () {
    it('lets only Roles with reports.view open each report', function (Role $role) {
        $this->actingAs(userWithRole($role));
        $allowed = in_array(Permission::ReportsView, $role->permissions(), true);

        foreach (['sales', 'products', 'categories', 'customers', 'returns', 'inventory'] as $name) {
            showReport($name)->assertStatus($allowed ? 200 : 403);
        }
    })->with(Role::cases());

    it('lets only Roles with reports.export download, separately from viewing', function (Role $role) {
        $this->actingAs(userWithRole($role));
        $allowed = in_array(Permission::ReportsExport, $role->permissions(), true);

        foreach (['csv', 'xlsx'] as $format) {
            $this->getJson("/api/v1/reports/inventory/export?format=$format")->assertStatus($allowed ? 200 : 403);
        }
    })->with(Role::cases());

    it('refuses a guest with 401', function () {
        asBrowser()->getJson('/api/v1/reports/sales')->assertUnauthorized();
        asBrowser()->getJson('/api/v1/reports/sales/export?format=csv')->assertUnauthorized();
    });
});
