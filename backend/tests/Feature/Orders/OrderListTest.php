<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

beforeEach(function () {
    [$this->beirut, $this->tripoli] = Warehouse::factory()->count(2)->create();
    $this->variant = Variant::factory()->create(['price' => '10.00']);
    receiveStock($this->beirut, [[$this->variant, 50, '4.00']]);
});

describe('listing Orders', function () {
    it('searches by number or Customer, filters by status, Warehouse and date, and sorts', function () {
        $rana = Customer::factory()->create(['name' => 'Rana Haddad', 'email' => 'rana@example.com']);
        Carbon::setTestNow('2026-10-01 10:00');
        $old = placeOrder($this->beirut, [[$this->variant, 1]], $rana);
        Carbon::setTestNow('2026-10-15 10:00');
        $big = placeOrder($this->tripoli, [[$this->variant, 5]]);
        Carbon::setTestNow('2026-10-20 10:00');
        $confirmed = placeOrder($this->beirut, [[$this->variant, 2]], $rana);
        Carbon::setTestNow();
        $this->actingAs(userWithRole(Role::Sales))->postJson("/api/v1/orders/$confirmed->id/confirm")->assertOk();

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.*.number', [$confirmed->number, $big->number, $old->number])
            ->assertJsonPath('data.0.customer.name', 'Rana Haddad')
            ->assertJsonPath('data.0.units', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/orders?search=rana@example')->assertJsonPath('data.*.id', [$confirmed->id, $old->id]);
        $this->getJson('/api/v1/orders?search='.$big->number)->assertJsonPath('data.*.id', [$big->id]);
        $this->getJson('/api/v1/orders?filter[status]=confirmed')->assertJsonPath('data.*.id', [$confirmed->id]);
        $this->getJson("/api/v1/orders?filter[warehouse]={$this->tripoli->id}")->assertJsonPath('data.*.id', [$big->id]);
        $this->getJson("/api/v1/orders?filter[customer]={$rana->id}")->assertJsonPath('data.*.id', [$confirmed->id, $old->id]);
        $this->getJson('/api/v1/orders?filter[from]=2026-10-10&filter[to]=2026-10-15')->assertJsonPath('data.*.id', [$big->id]);
        $this->getJson('/api/v1/orders?sort=-total')->assertJsonPath('data.0.id', $big->id);
        $this->getJson('/api/v1/orders?sort=placed_at')->assertJsonPath('data.0.id', $old->id);
    });

    it('refuses an unknown status, a reversed date range and unknown filters', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->getJson('/api/v1/orders?filter[status]=lost&filter[from]=2026-10-10&filter[to]=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.status', 'filter.to']);
        $this->getJson('/api/v1/orders?filter[colour]=red')->assertUnprocessable();
    });

    it('shows one Order with its lines, addresses, totals and status history', function () {
        $order = placeOrder($this->beirut, [[$this->variant, 2]]);

        $this->actingAs(userWithRole(Role::Finance))
            ->getJson("/api/v1/orders/$order->id")
            ->assertOk()
            ->assertJsonPath('data.number', $order->number)
            ->assertJsonPath('data.warehouse.id', $this->beirut->id)
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.lines.0.product_id', $this->variant->product_id)
            ->assertJsonPath('data.billing_address.city', 'Beirut')
            ->assertJsonPath('data.total', '26.00')
            ->assertJsonCount(1, 'data.status_history');
    });
});

describe('what Orders lock in place', function () {
    it('makes a Product on any Order archive-only', function () {
        placeOrder($this->beirut, [[$this->variant, 1]]);

        $this->actingAs(userWithRole(Role::Admin))
            ->deleteJson('/api/v1/products/'.$this->variant->product_id)
            ->assertConflict()
            ->assertJsonPath('code', 'in_use');

        expect(Product::query()->whereKey($this->variant->product_id)->exists())->toBeTrue();
    });

    it('keeps a Customer with Orders', function () {
        $customer = Customer::factory()->create();
        placeOrder($this->beirut, [[$this->variant, 1]], $customer);

        $this->actingAs(userWithRole(Role::Admin))
            ->deleteJson("/api/v1/customers/$customer->id")
            ->assertConflict()
            ->assertJsonPath('code', 'in_use');
    });
});

describe('finding Variants to add to an Order', function () {
    it('finds Variants of Active Products by SKU or Product name, with their price and Available stock', function () {
        $linen = Variant::factory()->for(Product::factory()->state(['name' => 'Linen Shirt']))->create(['sku' => 'LIN-M', 'price' => '45.00']);
        receiveStock($this->tripoli, [[$linen, 4, '10.00']]);
        Variant::factory()->for(Product::factory()->state(['name' => 'Linen Draft', 'status' => 'draft']))->create(['sku' => 'LIN-DRAFT']);

        $this->actingAs(userWithRole(Role::Sales))
            ->getJson('/api/v1/orders/sellable-variants?search=linen')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'LIN-M')
            ->assertJsonPath('data.0.price', '45.00')
            ->assertJsonPath('data.0.product.name', 'Linen Shirt')
            ->assertJsonPath('data.0.available', 4);

        $this->getJson('/api/v1/orders/sellable-variants?search='.$this->variant->sku)
            ->assertJsonPath('data.0.available', 50);
    });

    it('is refused to Roles that may not create Orders', function () {
        $this->actingAs(userWithRole(Role::Support))->getJson('/api/v1/orders/sellable-variants?search=a')->assertForbidden();
    });
});

describe('quoting an Order before it is created', function () {
    it('prices the lines and says which Warehouses can fulfil them', function () {
        $method = ShippingMethod::factory()->create([
            'shipping_zone_id' => ShippingZone::factory()->covering('LB')->create()->id,
            'price' => '7.50',
        ]);
        $cap = Variant::factory()->for(Product::factory()->state(['taxable' => false]))->create(['price' => '3.00', 'sku' => 'CAP-01']);
        receiveStock($this->tripoli, [[$this->variant, 3, '4.00'], [$cap, 9, '1.00']]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders/quote', [
                'shipping_method_id' => $method->id,
                'lines' => [['variant_id' => $this->variant->id, 'quantity' => 4], ['variant_id' => $cap->id, 'quantity' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.vat_amount', '2.00')
            ->assertJsonPath('data.lines.1.vat_amount', '0.00')
            ->assertJsonPath('data.items_total', '46.00')
            ->assertJsonPath('data.shipping_total', '7.50')
            ->assertJsonPath('data.total', '55.50')
            ->assertJsonPath('data.warehouses.0.id', $this->beirut->id)
            ->assertJsonPath('data.warehouses.0.is_sufficient', false)
            ->assertJsonPath('data.warehouses.0.shortages', [
                ['variant_id' => $cap->id, 'sku' => 'CAP-01', 'requested' => 2, 'available' => 0],
            ])
            ->assertJsonPath('data.warehouses.1.id', $this->tripoli->id)
            ->assertJsonPath('data.warehouses.1.is_sufficient', false)
            ->assertJsonPath('data.warehouses.1.shortages.0.available', 3);
    });

    it('quotes without a Shipping method, and lists a sufficient Warehouse as such', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders/quote', ['lines' => [['variant_id' => $this->variant->id, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.shipping_total', '0.00')
            ->assertJsonPath('data.total', '10.50')
            ->assertJsonPath('data.warehouses.0.is_sufficient', true);
    });

    it('is refused to Roles that may not create Orders', function () {
        $this->actingAs(userWithRole(Role::Finance))
            ->postJson('/api/v1/orders/quote', ['lines' => [['variant_id' => $this->variant->id, 'quantity' => 1]]])
            ->assertForbidden();
    });
});
