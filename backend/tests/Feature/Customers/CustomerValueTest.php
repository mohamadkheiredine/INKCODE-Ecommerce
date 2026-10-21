<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOutcome;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

/** An Order for the Customer with the given total, placed at the given moment, in the given status. */
function orderOfValue(Customer $customer, string $total, Carbon $placedAt, OrderStatus $status = OrderStatus::Confirmed): Order
{
    $order = placeOrder(test()->warehouse, [[test()->shirt, 1]], $customer);
    $order->update(['status' => $status, 'total' => $total, 'placed_at' => $placedAt]);

    return $order;
}

/** Adds a Refund (or a failed one) to the Order's Payment ledger. */
function refundOn(Order $order, string $amount, PaymentOutcome $outcome = PaymentOutcome::Succeeded): void
{
    $order->payments()->create(['kind' => PaymentKind::Refund, 'method' => PaymentMethod::Manual, 'amount' => $amount, 'status' => $outcome]);
}

/** The Customer's profile values, as the API sends them. */
function valueOf(Customer $customer): array
{
    return test()->getJson("/api/v1/customers/{$customer->id}")->assertOk()->json('data');
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
    $this->warehouse = Warehouse::factory()->create();
    $this->shirt = Variant::factory()->create(['price' => '20.00']);
    receiveStock($this->warehouse, [[$this->shirt, 100, '5.00']]);
    $this->actingAs(userWithRole(Role::Viewer));
});

describe('Lifetime value', function () {
    it('is the Revenue of Counted orders minus the Refunds in their Payment ledgers', function () {
        $customer = Customer::factory()->create();
        $placed = now()->subDays(40);
        $first = orderOfValue($customer, '100.00', $placed);
        orderOfValue($customer, '250.50', $placed, OrderStatus::Delivered);
        orderOfValue($customer, '999.00', $placed, OrderStatus::Pending);
        $cancelled = orderOfValue($customer, '500.00', $placed, OrderStatus::Cancelled);
        refundOn($first, '20.00');
        refundOn($first, '5.00', PaymentOutcome::Failed);
        refundOn($cancelled, '500.00');
        orderOfValue(Customer::factory()->create(), '70.00', $placed);

        expect(valueOf($customer))->toMatchArray([
            'lifetime_value' => '330.50',
            'last_order_at' => $placed->toIso8601String(),
            'segment' => 'regular',
        ]);
    });

    it('is zero, with no last Order, for a Customer without a Counted order', function () {
        $customer = Customer::factory()->create();
        orderOfValue($customer, '80.00', now()->subDays(5), OrderStatus::Pending);

        expect(valueOf($customer))->toMatchArray(['lifetime_value' => '0.00', 'last_order_at' => null, 'segment' => 'regular']);
    });

    it('comes with a new Customer', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/customers', ['name' => 'Rana Haddad', 'email' => 'rana@example.com'])
            ->assertCreated()
            ->assertJsonPath('data.lifetime_value', '0.00')
            ->assertJsonPath('data.segment', 'regular');
    });
});

describe('Segments', function () {
    it('is New while the first Counted order is at most 30 days old', function () {
        $new = Customer::factory()->create();
        orderOfValue($new, '50.00', now()->subDays(30));
        $older = Customer::factory()->create();
        orderOfValue($older, '50.00', now()->subDays(30)->subSecond());
        $repeat = Customer::factory()->create();
        orderOfValue($repeat, '50.00', now()->subDays(45));
        orderOfValue($repeat, '50.00', now()->subDay());

        expect(valueOf($new)['segment'])->toBe('new')
            ->and(valueOf($older)['segment'])->toBe('regular')
            ->and(valueOf($repeat)['segment'])->toBe('regular');
    });

    it('is VIP from a Lifetime value of $1,000, after Refunds', function () {
        $vip = Customer::factory()->create();
        orderOfValue($vip, '1000.00', now()->subDays(40));
        $justBelow = Customer::factory()->create();
        orderOfValue($justBelow, '999.99', now()->subDays(40));
        $refunded = Customer::factory()->create();
        refundOn(orderOfValue($refunded, '1200.00', now()->subDays(40)), '200.01');

        expect(valueOf($vip)['segment'])->toBe('vip')
            ->and(valueOf($justBelow)['segment'])->toBe('regular')
            ->and(valueOf($refunded))->toMatchArray(['lifetime_value' => '999.99', 'segment' => 'regular']);
    });

    it('is At risk once there has been no Counted order for 90 days', function () {
        $atRisk = Customer::factory()->create();
        orderOfValue($atRisk, '50.00', now()->subDays(90));
        $recent = Customer::factory()->create();
        orderOfValue($recent, '50.00', now()->subDays(90)->addSecond());
        $cancelledSince = Customer::factory()->create();
        orderOfValue($cancelledSince, '50.00', now()->subDays(120));
        orderOfValue($cancelledSince, '50.00', now()->subDays(10), OrderStatus::Cancelled);

        expect(valueOf($atRisk)['segment'])->toBe('at_risk')
            ->and(valueOf($recent)['segment'])->toBe('regular')
            ->and(valueOf($cancelledSince)['segment'])->toBe('at_risk');
    });

    it('takes the first match in the order New, VIP, At risk, Regular', function () {
        $newBigSpender = Customer::factory()->create();
        orderOfValue($newBigSpender, '2000.00', now()->subDays(3));
        $lapsedVip = Customer::factory()->create();
        orderOfValue($lapsedVip, '2000.00', now()->subDays(200));

        expect(valueOf($newBigSpender)['segment'])->toBe('new')
            ->and(valueOf($lapsedVip)['segment'])->toBe('vip');
    });
});

describe('listing by value', function () {
    beforeEach(function () {
        orderOfValue(Customer::factory()->create(['name' => 'Nadia New']), '40.00', now()->subDays(2));
        orderOfValue(Customer::factory()->create(['name' => 'Victor Vip']), '1500.00', now()->subDays(60));
        orderOfValue(Customer::factory()->create(['name' => 'Rami Risk']), '300.00', now()->subDays(100));
        orderOfValue(Customer::factory()->create(['name' => 'Rita Regular']), '600.00', now()->subDays(50));
        Customer::factory()->create(['name' => 'Zane None']);
    });

    it('shows each Customer\'s Lifetime value and Segment in the list', function () {
        $rows = collect($this->getJson('/api/v1/customers?sort=name')->assertOk()->json('data'))
            ->mapWithKeys(fn (array $row): array => [$row['name'] => [$row['lifetime_value'], $row['segment']]]);

        expect($rows->all())->toBe([
            'Nadia New' => ['40.00', 'new'],
            'Rami Risk' => ['300.00', 'at_risk'],
            'Rita Regular' => ['600.00', 'regular'],
            'Victor Vip' => ['1500.00', 'vip'],
            'Zane None' => ['0.00', 'regular'],
        ]);
    });

    it('filters by Segment, Lifetime value range and last Order date, and sorts by Lifetime value', function () {
        $names = fn (string $query): array => $this->getJson("/api/v1/customers?$query")->assertOk()->json('data.*.name');

        expect($names('filter[segment]=vip'))->toBe(['Victor Vip'])
            ->and($names('filter[segment]=at_risk'))->toBe(['Rami Risk'])
            ->and($names('filter[segment]=regular&sort=name'))->toBe(['Rita Regular', 'Zane None'])
            ->and($names('filter[lifetime_value_min]=300&filter[lifetime_value_max]=600&sort=name'))->toBe(['Rami Risk', 'Rita Regular'])
            ->and($names('filter[lifetime_value_max]=0'))->toBe(['Zane None'])
            ->and($names('filter[last_order_from]='.now()->subDays(60)->toDateString().'&filter[last_order_to]='.now()->subDays(50)->toDateString().'&sort=name'))->toBe(['Rita Regular', 'Victor Vip'])
            ->and($names('sort=-lifetime_value'))->toBe(['Victor Vip', 'Rita Regular', 'Rami Risk', 'Nadia New', 'Zane None'])
            ->and($names('sort=lifetime_value&per_page=2'))->toBe(['Zane None', 'Nadia New']);

        $this->getJson('/api/v1/customers?filter[segment]=vip&per_page=1')->assertJsonPath('meta.total', 1);
    });

    it('refuses an unknown Segment, a reversed range or a malformed date with 422', function () {
        $this->getJson('/api/v1/customers?filter[segment]=gold')->assertUnprocessable()->assertJsonValidationErrors('filter.segment');
        $this->getJson('/api/v1/customers?filter[lifetime_value_min]=500&filter[lifetime_value_max]=100')->assertUnprocessable()->assertJsonValidationErrors('filter.lifetime_value_max');
        $this->getJson('/api/v1/customers?filter[lifetime_value_min]=1.234')->assertUnprocessable()->assertJsonValidationErrors('filter.lifetime_value_min');
        $this->getJson('/api/v1/customers?filter[last_order_from]=2026-10-10&filter[last_order_to]=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('filter.last_order_to');
        $this->getJson('/api/v1/customers?filter[last_order_from]=yesterday')->assertUnprocessable()->assertJsonValidationErrors('filter.last_order_from');
    });
});

it('refuses Lifetime values and Segments to a Role without access to Customers', function (Role $role) {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole($role));
    $this->getJson('/api/v1/customers?filter[segment]=vip&sort=-lifetime_value')->assertForbidden();
    $this->getJson("/api/v1/customers/{$customer->id}")->assertForbidden();
})->with(fn (): array => array_values(array_filter(
    Role::cases(),
    fn (Role $role): bool => ! in_array(Permission::CustomersView, $role->permissions(), true),
)));
