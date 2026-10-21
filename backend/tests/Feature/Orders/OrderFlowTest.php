<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StoreSettings;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\Settings;

/**
 * The Variant's balance in the Warehouse as [On-hand, Reserved].
 *
 * @return array{int, int}
 */
function heldStock(Variant $variant, Warehouse $warehouse): array
{
    $balance = StockBalance::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id])->sole();

    return [$balance->on_hand, $balance->reserved];
}

function useDeductionStage(string $stage): void
{
    StoreSettings::query()->findOrFail(StoreSettings::ID)->update(['deduction_stage' => $stage]);
    app(Settings::class)->forget();
}

/** Moves the Order through the API, as an Operations User, up to the given status. */
function advanceTo(Order $order, OrderStatus $status): void
{
    $flow = [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::ReadyForShipping];
    test()->actingAs(userWithRole(Role::Operations));

    $done = array_search($order->refresh()->status, $flow, true);
    foreach ($flow as $position => $step) {
        if ($position > array_search($status, $flow, true)) {
            break;
        }
        if ($done !== false && $position <= $done) {
            continue;
        }
        $step === OrderStatus::Confirmed
            ? test()->postJson("/api/v1/orders/$order->id/confirm")->assertOk()
            : test()->postJson("/api/v1/orders/$order->id/advance", ['status' => $step->value])->assertOk();
    }
}

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create(['name' => 'Beirut Central']);
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
    $this->cap = Variant::factory()->create(['sku' => 'CAP-BLK', 'price' => '10.00']);
    Product::query()->whereKey([$this->shirt->product_id, $this->cap->product_id])->update(['taxable' => true]);
    receiveStock($this->warehouse, [[$this->shirt, 10, '5.00'], [$this->cap, 5, '3.00']]);
});

describe('moving an Order forward', function () {
    it('goes Confirmed → Preparing → Ready for shipping, one step at a time, with history and audit', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 2]]);
        $user = userWithRole(Role::Operations);
        $this->actingAs($user)->postJson("/api/v1/orders/$order->id/confirm")->assertOk();

        $this->postJson("/api/v1/orders/$order->id/advance", ['status' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.allowed_moves', ['ready_for_shipping', 'cancelled'])
            ->assertJsonPath('data.status_history.2.to_status', 'preparing')
            ->assertJsonPath('data.status_history.2.user.id', $user->id);
        $this->postJson("/api/v1/orders/$order->id/advance", ['status' => 'ready_for_shipping'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_shipping')
            ->assertJsonPath('data.allowed_moves', ['shipped', 'cancelled']);

        expect(auditTrailOf('order', $order->id)->last()?->only('before', 'after'))
            ->toBe(['before' => ['status' => 'preparing'], 'after' => ['status' => 'ready_for_shipping']]);
    });

    it('refuses skipped and backward moves with 409 and the allowed next steps', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 2]]);
        $this->actingAs(userWithRole(Role::Operations));

        $this->postJson("/api/v1/orders/$order->id/advance", ['status' => 'preparing'])
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_transition')
            ->assertJsonPath('details.allowed', ['confirmed', 'cancelled']);

        advanceTo($order, OrderStatus::ReadyForShipping);
        $this->postJson("/api/v1/orders/$order->id/advance", ['status' => 'preparing'])
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_transition')
            ->assertJsonPath('message', "Order $order->number is Ready for shipping, so it cannot become Preparing.")
            ->assertJsonPath('details.allowed', ['shipped', 'cancelled']);
    });

    it('only moves to Preparing or Ready for shipping through this endpoint (422)', function (string $status) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/orders/$order->id/advance", ['status' => $status])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    })->with(['confirmed', 'shipped', 'cancelled', 'unknown']);

    it('takes Reserved units out of On-hand when the Order reaches the Deduction stage, once', function () {
        useDeductionStage('preparing');
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);

        advanceTo($order, OrderStatus::Confirmed);
        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 3]);

        advanceTo($order, OrderStatus::ReadyForShipping);
        expect(heldStock($this->shirt, $this->warehouse))->toBe([7, 0])
            ->and(StockMovement::query()->where('type', 'deduction')->sole()->only('on_hand_delta', 'reserved_delta', 'source_id'))
            ->toBe(['on_hand_delta' => -3, 'reserved_delta' => -3, 'source_id' => $order->id]);
    });

    it('keeps the reservation until Shipped when that is the Deduction stage', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);

        advanceTo($order, OrderStatus::ReadyForShipping);

        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 3])
            ->and(StockMovement::query()->where('type', 'deduction')->count())->toBe(0);
    });

    it('is refused to Roles that may not move Orders', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        advanceTo($order, OrderStatus::Confirmed);

        $this->actingAs(userWithRole($role))
            ->postJson("/api/v1/orders/$order->id/advance", ['status' => 'preparing'])
            ->assertForbidden();
        expect($order->fresh()?->status)->toBe(OrderStatus::Confirmed);
    })->with([Role::Sales, Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('editing an Order', function () {
    beforeEach(function () {
        $this->order = placeOrder($this->warehouse, [[$this->shirt, 2], [$this->cap, 1]]);
        $this->address = CustomerAddress::query()->where('customer_id', $this->order->customer_id)->sole();
        $this->edit = fn (array $lines, array $overrides = []): array => [
            'billing_address_id' => $this->address->id,
            'shipping_address_id' => $this->address->id,
            'shipping_method_id' => $this->order->shipping_method_id,
            'lines' => $lines,
            ...$overrides,
        ];
    });

    it('re-reserves only the differences of a Confirmed Order and re-prices it', function () {
        $mug = Variant::factory()->create(['sku' => 'MUG-01', 'price' => '8.00']);
        Product::query()->whereKey($mug->product_id)->update(['taxable' => false]);
        receiveStock($this->warehouse, [[$mug, 4, '2.00']]);
        advanceTo($this->order, OrderStatus::Confirmed);

        $this->actingAs(userWithRole(Role::Sales))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)([
                ['variant_id' => $this->shirt->id, 'quantity' => 5],
                ['variant_id' => $mug->id, 'quantity' => 2],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.quantity', 5)
            ->assertJsonPath('data.lines.1.sku', 'MUG-01')
            ->assertJsonPath('data.lines.1.vat_amount', '0.00')
            // 5 × 20 + 2 × 8 = 116, VAT 5% of 100 = 5, shipping 5.
            ->assertJsonPath('data.items_total', '116.00')
            ->assertJsonPath('data.vat_total', '5.00')
            ->assertJsonPath('data.total', '126.00');

        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 5])
            ->and(heldStock($this->cap, $this->warehouse))->toBe([5, 0])
            ->and(heldStock($mug, $this->warehouse))->toBe([4, 2])
            ->and(auditTrailOf('order', $this->order->id)->last()?->after)->toHaveKeys(['lines', 'total']);
    });

    it('keeps the price a line was sold at when only its quantity changes', function () {
        $this->shirt->update(['price' => '99.00']);

        $this->actingAs(userWithRole(Role::Sales))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)([['variant_id' => $this->shirt->id, 'quantity' => 1]]))
            ->assertOk()
            ->assertJsonPath('data.lines.0.unit_price', '20.00')
            ->assertJsonPath('data.total', '26.00');
    });

    it('changes nothing when the extra units are not Available (409)', function () {
        advanceTo($this->order, OrderStatus::Confirmed);

        $this->actingAs(userWithRole(Role::Sales))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)([
                ['variant_id' => $this->shirt->id, 'quantity' => 1],
                ['variant_id' => $this->cap->id, 'quantity' => 6],
            ]))
            ->assertConflict()
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('details.lines.0.sku', 'CAP-BLK');

        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 2])
            ->and(heldStock($this->cap, $this->warehouse))->toBe([5, 1])
            ->and($this->order->fresh()?->total)->toBe($this->order->total);
    });

    it('moves a Pending Order\'s addresses and re-quotes shipping, without touching stock', function () {
        $express = ShippingMethod::factory()->create([
            'shipping_zone_id' => ShippingMethod::query()->findOrFail($this->order->shipping_method_id)->shipping_zone_id,
            'name' => 'Express',
            'price' => '12.00',
        ]);
        $office = CustomerAddress::factory()->create(['customer_id' => $this->order->customer_id, 'country_code' => 'LB', 'city' => 'Jounieh', 'line1' => 'Office tower']);

        $this->actingAs(userWithRole(Role::Sales))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)(
                [['variant_id' => $this->shirt->id, 'quantity' => 2], ['variant_id' => $this->cap->id, 'quantity' => 1]],
                ['shipping_address_id' => $office->id, 'shipping_method_id' => $express->id],
            ))
            ->assertOk()
            ->assertJsonPath('data.shipping_address.line1', 'Office tower')
            ->assertJsonPath('data.shipping_method.name', 'Express')
            ->assertJsonPath('data.shipping_total', '12.00');

        expect(StockMovement::query()->whereIn('type', ['reservation', 'release'])->count())->toBe(0);
    });

    it('refuses another Customer\'s address and a Variant of an inactive Product (422)', function () {
        $stranger = CustomerAddress::factory()->create();
        $draft = Variant::factory()->create();
        Product::query()->whereKey($draft->product_id)->update(['status' => 'draft']);

        $this->actingAs(userWithRole(Role::Sales))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)(
                [['variant_id' => $draft->id, 'quantity' => 1]],
                ['billing_address_id' => $stranger->id],
            ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_address_id', 'lines.0.variant_id']);
    });

    it('refuses to edit a Locked order with 409 order_locked', function (OrderStatus $status) {
        advanceTo($this->order, $status);

        $this->actingAs(userWithRole(Role::Admin))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)([['variant_id' => $this->shirt->id, 'quantity' => 1]]))
            ->assertConflict()
            ->assertJsonPath('code', 'order_locked');

        expect($this->order->fresh()?->lines)->toHaveCount(2);
    })->with([OrderStatus::Preparing, OrderStatus::ReadyForShipping]);

    it('is refused to Roles that may not edit Orders', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->putJson("/api/v1/orders/{$this->order->id}", ($this->edit)([['variant_id' => $this->shirt->id, 'quantity' => 1]]))
            ->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('Partial cancellation', function () {
    beforeEach(function () {
        $this->order = placeOrder($this->warehouse, [[$this->shirt, 4], [$this->cap, 2]]);
        $this->lines = $this->order->lines;
    });

    it('cancels some units, releases their reservation and re-prices the Order', function () {
        advanceTo($this->order, OrderStatus::Confirmed);
        $user = userWithRole(Role::Sales);

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", [
                'lines' => [['line_id' => $this->lines[0]->id, 'quantity' => 3], ['line_id' => $this->lines[1]->id, 'quantity' => 2]],
                'note' => 'Customer only wants one shirt',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.lines.0.quantity', 1)
            ->assertJsonPath('data.lines.0.cancelled_quantity', 3)
            ->assertJsonPath('data.lines.1.quantity', 0)
            ->assertJsonPath('data.lines.1.cancelled_quantity', 2)
            ->assertJsonPath('data.lines.1.line_total', '0.00')
            // 1 × 20 + VAT 1.00 + shipping 5.
            ->assertJsonPath('data.items_total', '20.00')
            ->assertJsonPath('data.total', '26.00')
            ->assertJsonPath('data.timeline.2.type', 'note')
            ->assertJsonPath('data.timeline.2.body', 'Customer only wants one shirt');

        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 1])
            ->and(heldStock($this->cap, $this->warehouse))->toBe([5, 0])
            ->and((int) StockMovement::query()->where('type', 'release')->sum('reserved_delta'))->toBe(-5);
    });

    it('puts deducted units back On-hand past the Deduction stage', function () {
        useDeductionStage('preparing');
        advanceTo($this->order, OrderStatus::Preparing);
        expect(heldStock($this->shirt, $this->warehouse))->toBe([6, 0]);

        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $this->lines[0]->id, 'quantity' => 1]]])
            ->assertOk();

        expect(heldStock($this->shirt, $this->warehouse))->toBe([7, 0])
            ->and(StockMovement::query()->where('type', 'cancellation_return')->sole()->on_hand_delta)->toBe(1);
    });

    it('cancels the whole Order when every remaining unit is cancelled', function () {
        advanceTo($this->order, OrderStatus::Confirmed);

        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", [
            'lines' => [['line_id' => $this->lines[0]->id, 'quantity' => 4], ['line_id' => $this->lines[1]->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 0]);
    });

    it('lets a Pending Order with a fully cancelled line be confirmed for what is left', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $this->lines[1]->id, 'quantity' => 2]]])
            ->assertOk();
        $this->postJson("/api/v1/orders/{$this->order->id}/confirm")->assertOk();

        expect(heldStock($this->shirt, $this->warehouse))->toBe([10, 4])
            ->and(heldStock($this->cap, $this->warehouse))->toBe([5, 0]);
    });

    it('refuses more units than the line has, or a line of another Order (422)', function () {
        $other = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $this->lines[1]->id, 'quantity' => 3]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity' => 'CAP-BLK has only 2 units left to cancel.']);
        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $other->lines[0]->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.line_id');
    });

    it('refuses a Cancelled Order (409)', function () {
        $this->actingAs(userWithRole(Role::Sales))->postJson("/api/v1/orders/{$this->order->id}/cancel")->assertOk();

        $this->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $this->lines[0]->id, 'quantity' => 1]]])
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_transition')
            ->assertJsonPath('details.allowed', []);
    });

    it('is refused to Roles that may not cancel Orders', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson("/api/v1/orders/{$this->order->id}/cancel-lines", ['lines' => [['line_id' => $this->lines[0]->id, 'quantity' => 1]]])
            ->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('the Assignee', function () {
    it('assigns an active Admin, Operations or Sales User, who is notified in the bell', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        $assignee = userWithRole(Role::Operations);

        $this->actingAs(userWithRole(Role::Sales))
            ->putJson("/api/v1/orders/$order->id/assignee", ['assignee_id' => $assignee->id])
            ->assertOk()
            ->assertJsonPath('data.assignee', ['id' => $assignee->id, 'name' => $assignee->name]);

        $notification = $assignee->notifications()->sole();
        expect($notification->type)->toBe('order_assigned')
            ->and($notification->data)->toEqual(['parameters' => ['number' => $order->number], 'link' => "/orders/$order->id"])
            ->and(auditTrailOf('order', $order->id)->last()?->after)->toBe(['assignee_id' => $assignee->id]);
    });

    it('assigns a Locked order too, and does not notify a User who assigns themselves', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        advanceTo($order, OrderStatus::Preparing);
        $self = userWithRole(Role::Admin);

        $this->actingAs($self)->putJson("/api/v1/orders/$order->id/assignee", ['assignee_id' => $self->id])->assertOk();
        $this->putJson("/api/v1/orders/$order->id/assignee", ['assignee_id' => null])
            ->assertOk()
            ->assertJsonPath('data.assignee', null);

        expect($self->notifications()->count())->toBe(0);
    });

    it('refuses an inactive User or one of another Role (422)', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        $this->actingAs(userWithRole(Role::Admin));

        foreach ([userWithRole(Role::Inventory), userWithRole(Role::Sales, ['is_active' => false])] as $user) {
            $this->putJson("/api/v1/orders/$order->id/assignee", ['assignee_id' => $user->id])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['assignee_id' => 'Choose an active Admin, Operations or Sales User.']);
        }
    });

    it('lists the Users an Order may be assigned to', function () {
        $sales = userWithRole(Role::Sales, ['name' => 'Aya Sales']);
        userWithRole(Role::Inventory, ['name' => 'Ian Inventory']);
        userWithRole(Role::Operations, ['name' => 'Omar Gone', 'is_active' => false]);

        $operations = userWithRole(Role::Operations, ['name' => 'Zed Ops']);

        $this->actingAs($operations)
            ->getJson('/api/v1/orders/assignees')
            ->assertOk()
            ->assertJsonPath('data', [['id' => $sales->id, 'name' => 'Aya Sales'], ['id' => $operations->id, 'name' => 'Zed Ops']]);
    });

    it('is refused to Roles that may not assign Orders', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        $this->actingAs(userWithRole($role));

        $this->putJson("/api/v1/orders/$order->id/assignee", ['assignee_id' => null])->assertForbidden();
        $this->getJson('/api/v1/orders/assignees')->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('notes and the timeline', function () {
    it('merges status changes and notes, oldest first, and takes notes on a Locked order', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        advanceTo($order, OrderStatus::Preparing);
        $author = userWithRole(Role::Sales);

        $this->actingAs($author)
            ->postJson("/api/v1/orders/$order->id/notes", ['body' => '  Gift wrap, please.  '])
            ->assertCreated()
            ->assertJsonCount(4, 'data.timeline')
            ->assertJsonPath('data.timeline.0.type', 'status')
            ->assertJsonPath('data.timeline.0.to_status', 'pending')
            ->assertJsonPath('data.timeline.2.to_status', 'preparing')
            ->assertJsonPath('data.timeline.3.type', 'note')
            ->assertJsonPath('data.timeline.3.body', 'Gift wrap, please.')
            ->assertJsonPath('data.timeline.3.user.id', $author->id);

        expect(auditTrailOf('order_note', OrderNote::query()->sole()->id)->sole()->record_label)->toBe($order->number);
    });

    it('requires a note of at most 2000 characters (422)', function (string $body) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/orders/$order->id/notes", ['body' => $body])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    })->with(['', str_repeat('a', 2001)]);

    it('is refused to Roles that may not edit Orders', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole($role))->postJson("/api/v1/orders/$order->id/notes", ['body' => 'Hi'])->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});
