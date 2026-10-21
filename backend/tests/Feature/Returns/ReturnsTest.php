<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\NotificationType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Carrier;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;

/**
 * The Variant's On-hand in the Warehouse, after checking that its balance equals the sum
 * of its Stock movements.
 */
function onHandOf(Variant $variant, Warehouse $warehouse): int
{
    $balance = StockBalance::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id])->sole();
    $movements = StockMovement::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id]);
    expect([(int) (clone $movements)->sum('on_hand_delta'), (int) $movements->sum('reserved_delta')])
        ->toBe([$balance->on_hand, $balance->reserved]);

    return $balance->on_hand;
}

/** Confirms, prepares, ships in one Shipment and delivers the Order, as an Operations User. */
function deliverOrder(Order $order, Carrier $carrier): Order
{
    test()->actingAs(userWithRole(Role::Operations));
    test()->postJson("/api/v1/orders/$order->id/confirm")->assertOk();
    test()->postJson("/api/v1/orders/$order->id/advance", ['status' => 'preparing'])->assertOk();
    test()->postJson("/api/v1/orders/$order->id/advance", ['status' => 'ready_for_shipping'])->assertOk();
    test()->postJson("/api/v1/orders/$order->id/shipments", [
        'carrier_id' => $carrier->id,
        'tracking_number' => 'AWB-'.$order->id,
        'lines' => $order->lines()->get()->map(fn ($line): array => ['line_id' => $line->id, 'quantity' => $line->quantity])->all(),
    ])->assertOk();
    $shipment = Shipment::query()->where('order_id', $order->id)->sole();
    test()->postJson("/api/v1/orders/$order->id/shipments/$shipment->id/deliver")->assertOk()->assertJsonPath('data.status', 'delivered');

    return $order->refresh();
}

/**
 * @param  array<int, int>  $units  units per Variant id
 */
function requestReturn(Order $order, array $units, string $reason = 'damaged'): TestResponse
{
    $lines = $order->lines()->get()->keyBy('variant_id');

    return test()->postJson("/api/v1/orders/$order->id/returns", [
        'reason' => $reason,
        'lines' => array_map(fn (int $variantId, int $quantity): array => ['line_id' => $lines[$variantId]->id, 'quantity' => $quantity], array_keys($units), $units),
    ]);
}

/** Requests, approves and receives a Return of the units, as an Admin; returns the Return. */
function receivedReturn(Order $order, array $units): OrderReturn
{
    test()->actingAs(userWithRole(Role::Admin));
    $id = requestReturn($order, $units)->assertCreated()->json('data.id');
    test()->postJson("/api/v1/returns/$id/approve")->assertOk();
    test()->postJson("/api/v1/returns/$id/receive")->assertOk();

    return OrderReturn::query()->findOrFail($id);
}

/** @return array<int, array{line_id: int, decision: string}> */
function decisionsFor(OrderReturn $return, string $decision): array
{
    return $return->lines()->get()->map(fn ($line): array => ['line_id' => $line->id, 'decision' => $decision])->all();
}

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create(['name' => 'Beirut Central']);
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
    $this->cap = Variant::factory()->create(['sku' => 'CAP-BLK', 'price' => '10.00']);
    Product::query()->whereKey([$this->shirt->product_id, $this->cap->product_id])->update(['taxable' => true]);
    receiveStock($this->warehouse, [[$this->shirt, 10, '5.00'], [$this->cap, 5, '3.00']]);
    $this->carrier = Carrier::factory()->create();
});

it('runs demo scenario 4: return one line of a paid Order with a Coupon, restock it, refund exactly and refuse above the cap', function () {
    Coupon::factory()->create(['code' => 'SAVE10']);
    $order = placeOrder($this->warehouse, [[$this->shirt, 2], [$this->cap, 1]], couponCode: 'SAVE10');
    // Items 40 + 10, 10% off → shares 4.00 + 1.00; 5% VAT on 36 + 9 → 1.80 + 0.45.
    $shirtLine = $order->lines()->where('variant_id', $this->shirt->id)->sole();
    expect($shirtLine->line_total)->toBe('37.80');

    $this->actingAs(userWithRole(Role::Finance));
    app(RecordPayment::class)->handle($order, PaymentMethod::Manual, $order->total, null, null, null);
    $order = deliverOrder($order, $this->carrier);
    $support = userWithRole(Role::Support);
    $sales = userWithRole(Role::Sales);
    expect(onHandOf($this->shirt, $this->warehouse))->toBe(8);

    $this->actingAs($support);
    $response = requestReturn($order, [$this->shirt->id => 1], 'damaged')
        ->assertCreated()
        ->assertJsonPath('data.number', sprintf('RET-%d-000001', now()->year))
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.reason', 'damaged')
        ->assertJsonPath('data.lines.0.quantity', 1)
        ->assertJsonPath('data.lines.0.paid_amount', '18.90')
        ->assertJsonPath('data.refund_suggestion.items', '18.90')
        ->assertJsonPath('data.refund_suggestion.shipping', '5.00');
    $id = $response->json('data.id');
    expect($support->notifications()->sole()->type)->toBe(NotificationType::ReturnRequested->value)
        ->and($sales->notifications()->count())->toBe(0);

    $this->postJson("/api/v1/returns/$id/approve", ['note' => 'Photo shows a torn seam'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decision_note', 'Photo shows a torn seam');
    $this->actingAs(userWithRole(Role::Inventory));
    $this->postJson("/api/v1/returns/$id/receive")->assertOk()->assertJsonPath('data.status', 'received');
    expect(onHandOf($this->shirt, $this->warehouse))->toBe(8);

    $finance = userWithRole(Role::Finance);
    $this->actingAs($finance);
    $this->postJson("/api/v1/returns/$id/refund", ['amount' => '18.90'])
        ->assertConflict()
        ->assertJsonPath('code', 'restock_decisions_pending');

    $this->actingAs(userWithRole(Role::Inventory));
    $return = OrderReturn::query()->findOrFail($id);
    $this->postJson("/api/v1/returns/$id/restock", ['lines' => decisionsFor($return, 'restock')])
        ->assertOk()
        ->assertJsonPath('data.lines.0.restock_decision', 'restock');
    expect(onHandOf($this->shirt, $this->warehouse))->toBe(9)
        ->and(StockMovement::query()->where('type', 'return_restock')->sole())
        ->source_type->toBe('return')
        ->on_hand_delta->toBe(1);

    $this->actingAs($finance);
    $this->postJson("/api/v1/returns/$id/refund", ['amount' => '18.91'])
        ->assertConflict()
        ->assertJsonPath('code', 'refund_exceeds_cap')
        ->assertJsonPath('details.cap', '18.90');
    $this->postJson("/api/v1/returns/$id/refund", ['amount' => '18.90'], ['Idempotency-Key' => 'ret-1'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.refund_amount', '18.90')
        ->assertJsonPath('data.refund.kind', 'refund')
        ->assertJsonPath('data.refund.amount', '18.90')
        ->assertJsonPath('data.refund.method', 'manual');
    $this->postJson("/api/v1/returns/$id/refund", ['amount' => '18.90'], ['Idempotency-Key' => 'ret-1'])
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    $refund = Payment::query()->where('kind', 'refund')->sole();
    expect($order->refresh())
        ->refunded_amount->toBe('18.90')
        ->payment_status->value->toBe('partially_refunded')
        ->and(OrderReturn::query()->findOrFail($id)->refund_id)->toBe($refund->id)
        ->and(auditTrailOf('return', $id)->pluck('action')->map->value->all())
        ->toBe(['created', 'status_changed', 'status_changed', 'updated', 'status_changed']);

    $this->getJson("/api/v1/orders/$order->id")
        ->assertOk()
        ->assertJsonPath('data.returns.0.id', $id)
        ->assertJsonPath('data.lines.0.returned_quantity', 1)
        ->assertJsonPath('data.payments.1.return.id', $id);
});

describe('requesting a Return', function () {
    beforeEach(function () {
        $this->order = placeOrder($this->warehouse, [[$this->shirt, 2], [$this->cap, 1]]);
    });

    it('refuses an Order that is not Delivered yet', function () {
        $this->actingAs(userWithRole(Role::Support));

        requestReturn($this->order, [$this->shirt->id => 1])
            ->assertConflict()
            ->assertJsonPath('code', 'order_not_delivered');
        expect(OrderReturn::query()->count())->toBe(0);
    });

    it('refuses a request after the Return window and accepts one on its last day', function () {
        $this->freezeSecond();
        $order = deliverOrder($this->order, $this->carrier);
        $this->actingAs(userWithRole(Role::Support));

        $this->travel(14)->days();
        requestReturn($order, [$this->shirt->id => 1])->assertCreated();
        $this->travel(1)->days();
        requestReturn($order, [$this->shirt->id => 1])
            ->assertConflict()
            ->assertJsonPath('code', 'return_window_closed');
    });

    it('refuses more units than delivered minus already returned, and frees the units of a Rejected Return', function () {
        $order = deliverOrder($this->order, $this->carrier);
        $this->actingAs(userWithRole(Role::Support));

        requestReturn($order, [$this->shirt->id => 3])
            ->assertConflict()
            ->assertJsonPath('code', 'return_exceeds_delivered')
            ->assertJsonPath('details.lines.0.returnable', 2);
        $first = requestReturn($order, [$this->shirt->id => 2])->assertCreated()->json('data.id');
        requestReturn($order, [$this->shirt->id => 1])
            ->assertConflict()
            ->assertJsonPath('details.lines.0.returnable', 0);

        $this->postJson("/api/v1/returns/$first/reject", ['note' => 'Outside policy'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
        requestReturn($order, [$this->shirt->id => 2, $this->cap->id => 1])
            ->assertCreated()
            ->assertJsonPath('data.number', sprintf('RET-%d-000002', now()->year));
    });

    it('validates the reason and the lines', function () {
        $other = placeOrder($this->warehouse, [[$this->cap, 1]]);
        $this->actingAs(userWithRole(Role::Support));

        $this->postJson("/api/v1/orders/{$this->order->id}/returns", ['reason' => 'bored', 'lines' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason', 'lines']);
        $this->postJson("/api/v1/orders/{$this->order->id}/returns", [
            'reason' => 'other',
            'lines' => [['line_id' => $other->lines()->sole()->id, 'quantity' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.line_id', 'lines.0.quantity']);
    });
});

describe('moving a Return', function () {
    beforeEach(function () {
        $this->order = deliverOrder(placeOrder($this->warehouse, [[$this->shirt, 2], [$this->cap, 1]]), $this->carrier);
    });

    it('allows only Requested → Approved or Rejected → Received → Completed', function () {
        $this->actingAs(userWithRole(Role::Admin));
        $id = requestReturn($this->order, [$this->shirt->id => 1])->json('data.id');

        $this->postJson("/api/v1/returns/$id/receive")->assertConflict()->assertJsonPath('code', 'invalid_return_transition');
        $this->postJson("/api/v1/returns/$id/refund", ['amount' => '0'])->assertConflict()->assertJsonPath('code', 'invalid_return_transition');
        $this->postJson("/api/v1/returns/$id/approve")->assertOk();
        $this->postJson("/api/v1/returns/$id/reject")->assertConflict()->assertJsonPath('code', 'invalid_return_transition');
        $this->postJson("/api/v1/returns/$id/restock", ['lines' => decisionsFor(OrderReturn::query()->findOrFail($id), 'restock')])
            ->assertConflict()
            ->assertJsonPath('code', 'return_not_received');
    });

    it('writes items off without changing stock and decides each line only once', function () {
        $return = receivedReturn($this->order, [$this->shirt->id => 1, $this->cap->id => 1]);
        $before = [onHandOf($this->shirt, $this->warehouse), onHandOf($this->cap, $this->warehouse)];
        [$shirtLine, $capLine] = decisionsFor($return, 'write_off');

        $this->postJson("/api/v1/returns/$return->id/restock", ['lines' => [$shirtLine]])->assertOk();
        $this->postJson("/api/v1/returns/$return->id/restock", ['lines' => [$shirtLine]])
            ->assertConflict()
            ->assertJsonPath('code', 'restock_already_decided');
        $this->postJson("/api/v1/returns/$return->id/restock", ['lines' => [['line_id' => $capLine['line_id'], 'decision' => 'restock']]])->assertOk();

        expect([onHandOf($this->shirt, $this->warehouse), onHandOf($this->cap, $this->warehouse)])->toBe([$before[0], $before[1] + 1]);
        $this->postJson("/api/v1/returns/$return->id/restock", ['lines' => [['line_id' => 999999, 'decision' => 'teleport']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.line_id', 'lines.0.decision']);
    });

    it('caps the Refund at the Captured amount minus earlier Refunds and completes an unpaid Return without one', function () {
        $return = receivedReturn($this->order, [$this->shirt->id => 1]);
        $this->postJson("/api/v1/returns/$return->id/restock", ['lines' => decisionsFor($return, 'write_off')])->assertOk();

        $this->postJson("/api/v1/returns/$return->id/refund", ['amount' => '0.01'])
            ->assertConflict()
            ->assertJsonPath('code', 'refund_exceeds_cap')
            ->assertJsonPath('details.cap', '0.00');
        $this->postJson("/api/v1/returns/$return->id/refund", ['amount' => '0'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.refund', null);
        expect(Payment::query()->count())->toBe(0);
    });

    it('adds the shipping when ticked, once per Order', function () {
        app(RecordPayment::class)->handle($this->order, PaymentMethod::Manual, $this->order->total, null, null, null);
        $first = receivedReturn($this->order, [$this->shirt->id => 1]);
        $this->postJson("/api/v1/returns/$first->id/restock", ['lines' => decisionsFor($first, 'restock')])->assertOk();
        $items = $this->getJson("/api/v1/returns/$first->id")->json('data.refund_suggestion.items');

        $this->postJson("/api/v1/returns/$first->id/refund", ['amount' => bcadd($items, '5.01', 2), 'refund_shipping' => true])
            ->assertConflict()
            ->assertJsonPath('details.cap', bcadd($items, '5.00', 2));
        $this->postJson("/api/v1/returns/$first->id/refund", ['amount' => bcadd($items, '5.00', 2), 'refund_shipping' => true])
            ->assertOk()
            ->assertJsonPath('data.refund_shipping', true);

        $second = receivedReturn($this->order, [$this->cap->id => 1]);
        $this->postJson("/api/v1/returns/$second->id/restock", ['lines' => decisionsFor($second, 'restock')])->assertOk();
        $this->getJson("/api/v1/returns/$second->id")->assertJsonPath('data.refund_suggestion.shipping', null);
        $this->postJson("/api/v1/returns/$second->id/refund", ['amount' => '1.00', 'refund_shipping' => true])
            ->assertConflict()
            ->assertJsonPath('code', 'shipping_already_refunded');
    });
});

describe('the Returns list', function () {
    it('searches by number, Order or Customer and filters by status and reason', function () {
        $order = deliverOrder(placeOrder($this->warehouse, [[$this->shirt, 2]]), $this->carrier);
        $this->actingAs(userWithRole(Role::Support));
        $id = requestReturn($order, [$this->shirt->id => 2], 'wrong_item')->json('data.id');

        $this->actingAs(userWithRole(Role::Viewer));
        $this->getJson('/api/v1/returns?filter[status]=requested&filter[reason]=wrong_item&search='.$order->number)
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.units', 2)
            ->assertJsonPath('data.0.order.customer.name', $order->customer->name);
        $this->getJson('/api/v1/returns?filter[status]=approved')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/returns/$id")->assertOk()->assertJsonPath('data.order.number', $order->number);
    });
});

describe('who may do what', function () {
    beforeEach(function () {
        $this->order = deliverOrder(placeOrder($this->warehouse, [[$this->shirt, 2]]), $this->carrier);
        $this->return = receivedReturn($this->order, [$this->shirt->id => 1]);
        $this->requested = OrderReturn::query()->findOrFail(requestReturn($this->order, [$this->shirt->id => 1])->json('data.id'));
    });

    it('refuses each step to Roles without its permission', function (string $step, array $allowed) {
        foreach (Role::cases() as $role) {
            if (in_array($role, $allowed, true)) {
                continue;
            }
            $this->actingAs(userWithRole($role));
            $response = match ($step) {
                'request' => requestReturn($this->order, [$this->shirt->id => 0]),
                'approve' => $this->postJson("/api/v1/returns/{$this->requested->id}/approve"),
                'reject' => $this->postJson("/api/v1/returns/{$this->requested->id}/reject"),
                'receive' => $this->postJson("/api/v1/returns/{$this->requested->id}/receive"),
                'restock' => $this->postJson("/api/v1/returns/{$this->return->id}/restock", ['lines' => decisionsFor($this->return, 'restock')]),
                'refund' => $this->postJson("/api/v1/returns/{$this->return->id}/refund", ['amount' => '0']),
            };
            $response->assertForbidden();
        }
        expect($this->requested->refresh()->status->value)->toBe('requested');
    })->with([
        'request' => ['request', [Role::Admin, Role::Support]],
        'approve' => ['approve', [Role::Admin, Role::Support]],
        'reject' => ['reject', [Role::Admin, Role::Support]],
        'receive' => ['receive', [Role::Admin, Role::Inventory]],
        'restock' => ['restock', [Role::Admin, Role::Inventory]],
        'refund' => ['refund', [Role::Admin, Role::Finance]],
    ]);

    it('lets every Role look at Returns', function () {
        foreach (Role::cases() as $role) {
            $this->actingAs(userWithRole($role));
            $this->getJson('/api/v1/returns')->assertOk();
            $this->getJson("/api/v1/returns/{$this->return->id}")->assertOk();
        }
    });
});
