<?php

use App\Enums\Role;
use App\Models\Attribute;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\StoreSettings;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\Settings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['name' => 'Rana Haddad']);
    $this->address = CustomerAddress::factory()->defaultForBoth()->create([
        'customer_id' => $this->customer->id,
        'recipient_name' => 'Rana Haddad',
        'country_code' => 'LB',
        'city' => 'Beirut',
        'line1' => 'Hamra Street 12',
    ]);
    $this->method = ShippingMethod::factory()->create([
        'shipping_zone_id' => ShippingZone::factory()->covering('LB')->create()->id,
        'name' => 'Standard',
        'price' => '5.00',
    ]);
    $this->warehouse = Warehouse::factory()->create();
});

/**
 * @param  list<array{variant_id: int, quantity: int}>  $lines
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function orderPayload(array $lines, array $overrides = []): array
{
    $test = test();

    return [
        'customer_id' => $test->customer->id,
        'billing_address_id' => $test->address->id,
        'shipping_address_id' => $test->address->id,
        'warehouse_id' => $test->warehouse->id,
        'shipping_method_id' => $test->method->id,
        'notes' => 'Leave at the door',
        'lines' => $lines,
        ...$overrides,
    ];
}

describe('creating an Order', function () {
    it('prices each line with VAT rounded per line, adds untaxed shipping and starts Pending', function () {
        $taxable = Variant::factory()->create(['price' => '19.99', 'sku' => 'TEE-RED-M']);
        $untaxed = Variant::factory()->for(Product::factory()->state(['taxable' => false]))->create(['price' => '10.00']);
        $user = userWithRole(Role::Sales);

        $order = $this->actingAs($user)
            ->postJson('/api/v1/orders', orderPayload([
                ['variant_id' => $taxable->id, 'quantity' => 3],
                ['variant_id' => $untaxed->id, 'quantity' => 1],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer.name', 'Rana Haddad')
            // 3 × 19.99 = 59.97, VAT 5% = 2.9985 → 3.00; the untaxed line has no VAT.
            ->assertJsonPath('data.lines.0.sku', 'TEE-RED-M')
            ->assertJsonPath('data.lines.0.vat_rate', '5.00')
            ->assertJsonPath('data.lines.0.vat_amount', '3.00')
            ->assertJsonPath('data.lines.0.line_total', '62.97')
            ->assertJsonPath('data.lines.1.vat_amount', '0.00')
            ->assertJsonPath('data.lines.1.line_total', '10.00')
            ->assertJsonPath('data.items_total', '69.97')
            ->assertJsonPath('data.vat_total', '3.00')
            ->assertJsonPath('data.shipping_total', '5.00')
            ->assertJsonPath('data.total', '77.97')
            ->assertJsonPath('data.shipping_method.name', 'Standard')
            ->assertJsonPath('data.shipping_address.city', 'Beirut')
            ->assertJsonPath('data.status_history.0.to_status', 'pending')
            ->assertJsonPath('data.status_history.0.user.id', $user->id)
            ->json('data');

        expect($order['number'])->toBe(sprintf('ORD-%s-000001', now()->format('Y')))
            ->and(auditTrailOf('order', $order['id'])->sole()->after)->toMatchArray([
                'number' => $order['number'],
                'status' => 'pending',
                'total' => '77.97',
            ]);
    });

    it('rounds VAT per line, not on the total: two lines of $2.50 at 5% carry $0.13 each', function () {
        [$first, $second] = Variant::factory()->count(2)->create(['price' => '2.50']);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders', orderPayload([
                ['variant_id' => $first->id, 'quantity' => 1],
                ['variant_id' => $second->id, 'quantity' => 1],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.lines.0.vat_amount', '0.13')
            ->assertJsonPath('data.vat_total', '0.26')
            ->assertJsonPath('data.total', '10.26');
    });

    it('keeps a snapshot, so later edits to the Product, the address or the VAT rate never change it', function () {
        $red = Attribute::factory()->withValues(['Red'])->create(['name' => 'Color'])->values()->sole();
        $variant = Variant::factory()->for(Product::factory()->state(['name' => 'Linen shirt']))->create(['price' => '40.00', 'sku' => 'LIN-RED']);
        $variant->attributeValues()->attach($red);
        $this->actingAs(userWithRole(Role::Sales));

        $id = $this->postJson('/api/v1/orders', orderPayload([['variant_id' => $variant->id, 'quantity' => 1]]))
            ->assertCreated()
            ->json('data.id');

        $variant->product->update(['name' => 'Renamed shirt']);
        $variant->update(['price' => '99.00', 'sku' => 'NEW-SKU']);
        $this->address->update(['city' => 'Tripoli']);
        StoreSettings::query()->whereKey(StoreSettings::ID)->update(['vat_rate' => '11.00']);
        app(Settings::class)->forget();

        $this->getJson("/api/v1/orders/$id")
            ->assertOk()
            ->assertJsonPath('data.lines.0.product_name', 'Linen shirt')
            ->assertJsonPath('data.lines.0.sku', 'LIN-RED')
            ->assertJsonPath('data.lines.0.options', [['attribute' => 'Color', 'value' => 'Red']])
            ->assertJsonPath('data.lines.0.unit_price', '40.00')
            ->assertJsonPath('data.lines.0.vat_rate', '5.00')
            ->assertJsonPath('data.lines.0.vat_amount', '2.00')
            ->assertJsonPath('data.shipping_address.city', 'Beirut')
            ->assertJsonPath('data.total', '47.00');
    });

    it('numbers Orders from a sequence per year', function () {
        $variant = Variant::factory()->create();
        $this->actingAs(userWithRole(Role::Sales));

        Carbon::setTestNow('2026-12-31 23:00:00');
        $this->postJson('/api/v1/orders', orderPayload([['variant_id' => $variant->id, 'quantity' => 1]]))
            ->assertJsonPath('data.number', 'ORD-2026-000001');
        $this->postJson('/api/v1/orders', orderPayload([['variant_id' => $variant->id, 'quantity' => 1]]))
            ->assertJsonPath('data.number', 'ORD-2026-000002');

        Carbon::setTestNow('2027-01-01 08:00:00');
        $this->postJson('/api/v1/orders', orderPayload([['variant_id' => $variant->id, 'quantity' => 1]]))
            ->assertJsonPath('data.number', 'ORD-2027-000001');
    });

    it('refuses an address of another Customer, a missing line, a repeated Variant and bad quantities', function () {
        $variant = Variant::factory()->create();
        $strangersAddress = CustomerAddress::factory()->defaultForBoth()->create();

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders', orderPayload([
                ['variant_id' => $variant->id, 'quantity' => 0],
                ['variant_id' => $variant->id, 'quantity' => 1],
            ], ['shipping_address_id' => $strangersAddress->id, 'customer_id' => $this->customer->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shipping_address_id', 'lines.0.variant_id', 'lines.0.quantity']);

        $this->postJson('/api/v1/orders', orderPayload([]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);

        expect(Order::query()->count())->toBe(0);
    });

    it('refuses a Shipping method that does not deliver to the shipping address', function () {
        $elsewhere = ShippingMethod::factory()->create([
            'shipping_zone_id' => ShippingZone::factory()->covering('AE')->create()->id,
        ]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders', orderPayload(
                [['variant_id' => Variant::factory()->create()->id, 'quantity' => 1]],
                ['shipping_method_id' => $elsewhere->id],
            ))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'shipping_method_unavailable');
    });

    it('refuses an inactive Warehouse and an Archived Product', function () {
        $archived = Variant::factory()->for(Product::factory()->state(['status' => 'archived']))->create();

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders', orderPayload(
                [['variant_id' => $archived->id, 'quantity' => 1]],
                ['warehouse_id' => Warehouse::factory()->inactive()->create()->id],
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warehouse_id', 'lines.0.variant_id']);
    });

    it('is refused to Roles that may not create Orders', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson('/api/v1/orders', orderPayload([['variant_id' => Variant::factory()->create()->id, 'quantity' => 1]]))
            ->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('retrying with an Idempotency-Key', function () {
    it('creates the Order once and replays the first answer', function () {
        $payload = orderPayload([['variant_id' => Variant::factory()->create()->id, 'quantity' => 2]]);
        $this->actingAs(userWithRole(Role::Sales));

        $first = $this->withHeader('Idempotency-Key', 'order-1')->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', 'order-1')->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.number', $first->json('data.number'));

        expect(Order::query()->count())->toBe(1);
    });

    it('refuses the same key with a different payload with 409', function () {
        $variant = Variant::factory()->create();
        $this->actingAs(userWithRole(Role::Sales));

        $this->withHeader('Idempotency-Key', 'order-2')
            ->postJson('/api/v1/orders', orderPayload([['variant_id' => $variant->id, 'quantity' => 1]]))
            ->assertCreated();
        $this->withHeader('Idempotency-Key', 'order-2')
            ->postJson('/api/v1/orders', orderPayload([['variant_id' => $variant->id, 'quantity' => 5]]))
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_key_reused');

        expect(Order::query()->count())->toBe(1);
    });
});
