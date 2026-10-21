<?php

use App\Enums\Role;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StoreSettings;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

/**
 * The Variant's balance in the Warehouse as [On-hand, Reserved], after checking that it
 * equals the sum of its Stock movements.
 *
 * @return array{int, int}
 */
function ledgerBalance(Variant $variant, Warehouse $warehouse): array
{
    $balance = StockBalance::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id])->sole();
    $movements = StockMovement::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id]);
    expect([(int) (clone $movements)->sum('on_hand_delta'), (int) $movements->sum('reserved_delta')])
        ->toBe([$balance->on_hand, $balance->reserved]);

    return [$balance->on_hand, $balance->reserved];
}

function deductAt(string $stage): void
{
    StoreSettings::query()->findOrFail(StoreSettings::ID)->update(['deduction_stage' => $stage]);
    app(Settings::class)->forget();
}

/** Moves the Order forward through the API, as an Operations User, through the given steps. */
function moveOrder(Order $order, string ...$steps): void
{
    test()->actingAs(userWithRole(Role::Operations));
    foreach ($steps as $step) {
        $step === 'confirmed'
            ? test()->postJson("/api/v1/orders/$order->id/confirm")->assertOk()
            : test()->postJson("/api/v1/orders/$order->id/advance", ['status' => $step])->assertOk();
    }
}

/**
 * @param  array<int, int>  $units  units per Variant id
 * @return TestResponse<JsonResponse>
 */
function shipUnits(Order $order, Carrier $carrier, string $tracking, array $units): TestResponse
{
    $lines = $order->lines()->get()->keyBy('variant_id');

    return test()->postJson("/api/v1/orders/$order->id/shipments", [
        'carrier_id' => $carrier->id,
        'tracking_number' => $tracking,
        'lines' => array_map(fn (int $variantId, int $quantity): array => ['line_id' => $lines[$variantId]->id, 'quantity' => $quantity], array_keys($units), $units),
    ]);
}

function deductions(): int
{
    return StockMovement::query()->where('type', 'deduction')->count();
}

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create(['name' => 'Beirut Central']);
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
    $this->cap = Variant::factory()->create(['sku' => 'CAP-BLK', 'price' => '10.00']);
    Product::query()->whereKey([$this->shirt->product_id, $this->cap->product_id])->update(['taxable' => true]);
    receiveStock($this->warehouse, [[$this->shirt, 10, '5.00'], [$this->cap, 5, '3.00']]);
    $this->carrier = Carrier::factory()->create(['name' => 'Aramex', 'tracking_url_template' => 'https://track.example/?n={tracking_number}']);
});

it('runs demo scenario 3: create → confirm → prepare → ready → ship in 2 Shipments → deliver', function () {
    $order = placeOrder($this->warehouse, [[$this->shirt, 3], [$this->cap, 2]]);
    expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([10, 0]);

    $this->actingAs(userWithRole(Role::Operations));
    shipUnits($order, $this->carrier, 'AWB-0', [$this->shirt->id => 1])
        ->assertConflict()
        ->assertJsonPath('code', 'order_not_ready_for_shipping');

    moveOrder($order, 'confirmed');
    expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([10, 3])
        ->and(ledgerBalance($this->cap, $this->warehouse))->toBe([5, 2]);
    $this->postJson("/api/v1/orders/$order->id/advance", ['status' => 'ready_for_shipping'])
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_transition');

    moveOrder($order, 'preparing');
    shipUnits($order, $this->carrier, 'AWB-0', [$this->shirt->id => 1])->assertConflict();
    moveOrder($order, 'ready_for_shipping');
    expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([10, 3]);

    shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 2])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_shipping')
        ->assertJsonPath('data.fulfillment', ['status' => 'partially_shipped', 'shipped_units' => 2, 'units' => 5])
        ->assertJsonPath('data.lines.0.shipped_quantity', 2)
        ->assertJsonPath('data.shipments.0.number', "$order->number-1")
        ->assertJsonPath('data.shipments.0.status', 'shipped')
        ->assertJsonPath('data.shipments.0.carrier.name', 'Aramex')
        ->assertJsonPath('data.shipments.0.tracking_url', 'https://track.example/?n=AWB-1')
        ->assertJsonPath('data.shipments.0.status_history.0.to_status', 'shipped');
    expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([8, 1])
        ->and(ledgerBalance($this->cap, $this->warehouse))->toBe([5, 2]);

    $this->postJson("/api/v1/orders/$order->id/advance", ['status' => 'shipped'])->assertUnprocessable();

    shipUnits($order, $this->carrier, 'AWB-2', [$this->shirt->id => 1, $this->cap->id => 2])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.fulfillment', ['status' => 'shipped', 'shipped_units' => 5, 'units' => 5]);
    expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0])
        ->and(ledgerBalance($this->cap, $this->warehouse))->toBe([3, 0]);

    shipUnits($order, $this->carrier, 'AWB-3', [$this->shirt->id => 1])
        ->assertConflict()
        ->assertJsonPath('code', 'order_not_ready_for_shipping');
    $this->postJson("/api/v1/orders/$order->id/cancel")->assertConflict()->assertJsonPath('code', 'invalid_transition');

    [$first, $second] = Shipment::query()->orderBy('id')->get()->all();
    $this->postJson("/api/v1/orders/$order->id/shipments/$first->id/deliver")
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.shipments.0.status', 'delivered')
        ->assertJsonPath('data.shipments.0.status_history.1.from_status', 'shipped')
        ->assertJsonPath('data.fulfillment.status', 'shipped');
    $this->postJson("/api/v1/orders/$order->id/shipments/$second->id/deliver")
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.fulfillment.status', 'delivered')
        ->assertJsonPath('data.allowed_moves', []);
    $this->postJson("/api/v1/orders/$order->id/shipments/$second->id/deliver")
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_shipment_transition');

    expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0])
        ->and(deductions())->toBe(3)
        ->and($order->statusChanges()->pluck('to_status')->map->value->all())
        ->toBe(['pending', 'confirmed', 'preparing', 'ready_for_shipping', 'shipped', 'delivered']);
});

it('shows Shipments in the timeline and records them in the audit trail', function () {
    $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
    moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');

    $response = shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertOk();
    $shipment = Shipment::query()->sole();

    $events = collect($response->json('data.timeline'))->where('type', 'shipment')->values();
    expect($events->all())->toHaveCount(1)
        ->and($events[0]['shipment']['number'])->toBe($shipment->number)
        ->and($events[0]['to_status'])->toBe('shipped')
        ->and(auditTrailOf('shipment', $shipment->id)->sole()->record_label)->toBe($shipment->number)
        ->and(auditTrailOf('shipment', $shipment->id)->sole()->action->value)->toBe('created');

    $this->postJson("/api/v1/orders/$order->id/shipments/$shipment->id/deliver")->assertOk();
    expect(auditTrailOf('shipment', $shipment->id)->last()?->only('before', 'after'))
        ->toBe(['before' => ['status' => 'shipped'], 'after' => ['status' => 'delivered']]);
});

it('gives no tracking link when the Carrier has none', function () {
    $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
    moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');

    shipUnits($order, Carrier::factory()->create(), 'X1', [$this->shirt->id => 1])
        ->assertOk()
        ->assertJsonPath('data.shipments.0.tracking_url', null);
});

describe('the Deduction stage', function () {
    it('deducts the whole Order on entering Preparing, and Shipments deduct nothing more', function (string $stage, array $before) {
        deductAt($stage);
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);

        moveOrder($order, 'confirmed', 'preparing');
        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe($before);
        moveOrder($order, 'ready_for_shipping');
        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0]);

        shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertOk();
        shipUnits($order, $this->carrier, 'AWB-2', [$this->shirt->id => 2])->assertOk()->assertJsonPath('data.status', 'shipped');

        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0])
            ->and(deductions())->toBe(1);
    })->with([
        'Preparing' => ['preparing', [7, 0]],
        'Ready for shipping' => ['ready_for_shipping', [10, 3]],
    ]);

    it('never deducts twice when the setting moves later mid-flight', function () {
        deductAt('preparing');
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        moveOrder($order, 'confirmed', 'preparing');
        deductAt('shipped');
        moveOrder($order, 'ready_for_shipping');

        shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 3])->assertOk();

        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0])
            ->and(deductions())->toBe(1);
    });

    it('deducts only what is left when the setting moves earlier mid-flight', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        moveOrder($order, 'confirmed', 'preparing');
        deductAt('preparing');
        moveOrder($order, 'ready_for_shipping');
        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0]);

        shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 3])->assertOk();
        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0]);
    });

    it('deducts the unshipped rest once the setting moves earlier after a first Shipment', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');
        shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertOk();
        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([9, 2]);

        deductAt('preparing');
        shipUnits($order, $this->carrier, 'AWB-2', [$this->shirt->id => 2])->assertOk();

        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0])
            ->and(deductions())->toBe(2);
    });
});

describe('Shipment quantities', function () {
    beforeEach(function () {
        $this->order = placeOrder($this->warehouse, [[$this->shirt, 3], [$this->cap, 2]]);
        moveOrder($this->order, 'confirmed', 'preparing', 'ready_for_shipping');
    });

    it('refuses more units than are unshipped and uncancelled (422)', function () {
        shipUnits($this->order, $this->carrier, 'AWB-1', [$this->shirt->id => 2])->assertOk();
        shipUnits($this->order, $this->carrier, 'AWB-2', [$this->shirt->id => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity' => 'TEE-RED-M has only 1 units left to ship.']);

        $capLine = $this->order->lines()->where('variant_id', $this->cap->id)->sole();
        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $capLine->id, 'quantity' => 1]]])->assertOk();
        shipUnits($this->order, $this->carrier, 'AWB-2', [$this->cap->id => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.quantity');

        expect(Shipment::query()->count())->toBe(1)
            ->and(ledgerBalance($this->shirt, $this->warehouse))->toBe([8, 1]);
    });

    it('validates the Carrier, tracking number and lines (422)', function () {
        shipUnits($this->order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertOk();
        $other = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        shipUnits($this->order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertJsonValidationErrors('tracking_number');
        $this->postJson("/api/v1/orders/{$this->order->id}/shipments", [
            'carrier_id' => 999999,
            'tracking_number' => '',
            'lines' => [['line_id' => $other->lines()->value('id'), 'quantity' => 0]],
        ])->assertJsonValidationErrors(['carrier_id', 'tracking_number', 'lines.0.line_id', 'lines.0.quantity']);
        $this->postJson("/api/v1/orders/{$this->order->id}/shipments", ['carrier_id' => $this->carrier->id, 'tracking_number' => 'AWB-9', 'lines' => []])
            ->assertJsonValidationErrors('lines');

        expect(Shipment::query()->count())->toBe(1);
    });

    it('is safe to retry with the same Idempotency-Key', function () {
        $line = $this->order->lines()->where('variant_id', $this->shirt->id)->sole();
        $body = ['carrier_id' => $this->carrier->id, 'tracking_number' => 'AWB-1', 'lines' => [['line_id' => $line->id, 'quantity' => 1]]];

        $this->postJson("/api/v1/orders/{$this->order->id}/shipments", $body, ['Idempotency-Key' => 'ship-1'])->assertOk();
        $this->postJson("/api/v1/orders/{$this->order->id}/shipments", $body, ['Idempotency-Key' => 'ship-1'])->assertOk();

        expect(Shipment::query()->count())->toBe(1)
            ->and(ledgerBalance($this->shirt, $this->warehouse))->toBe([9, 2]);
    });

    it('cancels only unshipped units, and the Order ships once only shipped units are left', function () {
        shipUnits($this->order, $this->carrier, 'AWB-1', [$this->shirt->id => 3, $this->cap->id => 1])->assertOk();
        $capLine = $this->order->lines()->where('variant_id', $this->cap->id)->sole();
        $shirtLine = $this->order->lines()->where('variant_id', $this->shirt->id)->sole();

        $this->postJson("/api/v1/orders/{$this->order->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('code', 'order_partly_shipped');
        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $shirtLine->id, 'quantity' => 1]]])
            ->assertJsonValidationErrors('lines.0.quantity');

        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $capLine->id, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.fulfillment', ['status' => 'shipped', 'shipped_units' => 4, 'units' => 4]);

        expect(ledgerBalance($this->cap, $this->warehouse))->toBe([4, 0])
            ->and(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 0]);
    });

    it('puts deducted, unshipped units back On-hand when they are cancelled', function () {
        deductAt('preparing');
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');
        shipUnits($order, $this->carrier, 'AWB-5', [$this->shirt->id => 1])->assertOk();
        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([7, 3]);

        $line = $order->lines()->sole();
        $this->postJson("/api/v1/orders/$order->id/cancel-lines", ['lines' => [['line_id' => $line->id, 'quantity' => 2]]])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');

        expect(ledgerBalance($this->shirt, $this->warehouse))->toBe([9, 3])
            ->and($line->refresh()->only('quantity', 'deducted_quantity', 'shipped_quantity'))
            ->toBe(['quantity' => 1, 'deducted_quantity' => 1, 'shipped_quantity' => 1]);
    });
});

describe('access', function () {
    it('lets Roles with shipments.manage ship and deliver', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');

        $this->actingAs(userWithRole($role));
        shipUnits($order, $this->carrier, "AWB-$role->value", [$this->shirt->id => 1])->assertOk();
        $this->postJson("/api/v1/orders/$order->id/shipments/".Shipment::query()->sole()->id.'/deliver')->assertOk();
    })->with([Role::Admin, Role::Operations, Role::Inventory]);

    it('refuses Roles that may not ship (403)', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 2]]);
        moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');
        shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertOk();
        $shipment = Shipment::query()->sole();

        $this->actingAs(userWithRole($role));
        shipUnits($order, $this->carrier, 'AWB-2', [$this->shirt->id => 1])->assertForbidden();
        $this->postJson("/api/v1/orders/$order->id/shipments/$shipment->id/deliver")->assertForbidden();

        expect(Shipment::query()->count())->toBe(1)
            ->and($shipment->refresh()->status->value)->toBe('shipped');
    })->with([Role::Sales, Role::Support, Role::Finance, Role::Viewer]);

    it('only delivers a Shipment through its own Order (404)', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        $other = placeOrder($this->warehouse, [[$this->cap, 1]]);
        moveOrder($order, 'confirmed', 'preparing', 'ready_for_shipping');
        shipUnits($order, $this->carrier, 'AWB-1', [$this->shirt->id => 1])->assertOk();

        $this->postJson("/api/v1/orders/$other->id/shipments/".Shipment::query()->sole()->id.'/deliver')->assertNotFound();
    });
});
