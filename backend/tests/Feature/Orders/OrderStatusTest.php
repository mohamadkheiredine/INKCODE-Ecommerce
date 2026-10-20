<?php

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Models\Warehouse;

/**
 * The Variant's balance in the Warehouse as [On-hand, Reserved].
 *
 * @return array{int, int}
 */
function stockOf(Variant $variant, Warehouse $warehouse): array
{
    $balance = StockBalance::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id])->sole();

    return [$balance->on_hand, $balance->reserved];
}

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create(['name' => 'Beirut Central']);
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M']);
    $this->cap = Variant::factory()->create(['sku' => 'CAP-BLK']);
    receiveStock($this->warehouse, [[$this->shirt, 10, '5.00'], [$this->cap, 2, '3.00']]);
});

describe('confirming an Order', function () {
    it('reserves every line in the Fulfilling warehouse and records the status change', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3], [$this->cap, 2]]);
        $user = userWithRole(Role::Sales);

        $this->actingAs($user)
            ->postJson("/api/v1/orders/$order->id/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_history.1.from_status', 'pending')
            ->assertJsonPath('data.status_history.1.to_status', 'confirmed')
            ->assertJsonPath('data.status_history.1.user.id', $user->id);

        expect(stockOf($this->shirt, $this->warehouse))->toBe([10, 3])
            ->and(stockOf($this->cap, $this->warehouse))->toBe([2, 2])
            ->and(StockMovement::query()->where('type', 'reservation')->get()->map->only('variant_id', 'reserved_delta', 'source_type', 'source_id', 'user_id')->all())
            ->toBe([
                ['variant_id' => $this->shirt->id, 'reserved_delta' => 3, 'source_type' => 'order', 'source_id' => $order->id, 'user_id' => $user->id],
                ['variant_id' => $this->cap->id, 'reserved_delta' => 2, 'source_type' => 'order', 'source_id' => $order->id, 'user_id' => $user->id],
            ])
            ->and(auditTrailOf('order', $order->id)->last()?->only('action', 'before', 'after'))
            ->toBe(['action' => AuditAction::StatusChanged, 'before' => ['status' => 'pending'], 'after' => ['status' => 'confirmed']]);
    });

    it('reserves nothing and names every line that lacks stock', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 11], [$this->cap, 2], [$third = Variant::factory()->create(['sku' => 'MUG-01']), 1]]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/orders/$order->id/confirm")
            ->assertConflict()
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('message', 'Not enough Available stock in Beirut Central for TEE-RED-M (11 requested, 10 available), MUG-01 (1 requested, 0 available).')
            ->assertJsonPath('details.lines', [
                ['variant_id' => $this->shirt->id, 'sku' => 'TEE-RED-M', 'requested' => 11, 'available' => 10],
                ['variant_id' => $third->id, 'sku' => 'MUG-01', 'requested' => 1, 'available' => 0],
            ]);

        expect($order->fresh()?->status)->toBe(OrderStatus::Pending)
            ->and(stockOf($this->shirt, $this->warehouse))->toBe([10, 0])
            ->and(stockOf($this->cap, $this->warehouse))->toBe([2, 0])
            ->and(StockMovement::query()->where('type', 'reservation')->count())->toBe(0);
    });

    it('counts stock already reserved by other Orders', function () {
        $this->actingAs(userWithRole(Role::Sales));
        $first = placeOrder($this->warehouse, [[$this->shirt, 7]]);
        $second = placeOrder($this->warehouse, [[$this->shirt, 4]]);

        $this->postJson("/api/v1/orders/$first->id/confirm")->assertOk();
        $this->postJson("/api/v1/orders/$second->id/confirm")
            ->assertConflict()
            ->assertJsonPath('details.lines.0.available', 3);
    });

    it('refuses to confirm an Order twice (409), and a retry with the same key replays the answer', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        $this->actingAs(userWithRole(Role::Operations));

        $this->withHeader('Idempotency-Key', 'confirm-1')->postJson("/api/v1/orders/$order->id/confirm")->assertOk();
        $this->withHeader('Idempotency-Key', 'confirm-1')->postJson("/api/v1/orders/$order->id/confirm")
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');
        $this->withHeader('Idempotency-Key', 'confirm-2')->postJson("/api/v1/orders/$order->id/confirm")
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_transition');

        expect(stockOf($this->shirt, $this->warehouse))->toBe([10, 3]);
    });

    it('is refused to Roles that may not confirm Orders', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole($role))->postJson("/api/v1/orders/$order->id/confirm")->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});

describe('cancelling an Order', function () {
    it('releases the Reserved stock of a Confirmed Order and records why', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        $this->actingAs(userWithRole(Role::Sales));
        $this->postJson("/api/v1/orders/$order->id/confirm")->assertOk();

        $this->postJson("/api/v1/orders/$order->id/cancel", ['note' => 'Customer changed their mind'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.status_history.2.from_status', 'confirmed')
            ->assertJsonPath('data.status_history.2.note', 'Customer changed their mind');

        $release = StockMovement::query()->where('type', 'release')->sole();
        expect(stockOf($this->shirt, $this->warehouse))->toBe([10, 0])
            ->and($release->only('reserved_delta', 'source_type', 'source_id', 'reason'))
            ->toBe(['reserved_delta' => -3, 'source_type' => 'order', 'source_id' => $order->id, 'reason' => 'Customer changed their mind']);
    });

    it('cancels a Pending Order without touching stock', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/orders/$order->id/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        expect(StockMovement::query()->whereIn('type', ['reservation', 'release'])->count())->toBe(0);
    });

    it('refuses to cancel twice and to confirm a Cancelled Order', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        $this->actingAs(userWithRole(Role::Sales));
        $this->postJson("/api/v1/orders/$order->id/cancel")->assertOk();

        $this->postJson("/api/v1/orders/$order->id/cancel")
            ->assertConflict()
            ->assertJsonPath('code', 'invalid_transition')
            ->assertJsonPath('message', "Order $order->number is Cancelled, so it cannot become Cancelled.");
        $this->postJson("/api/v1/orders/$order->id/confirm")->assertConflict();
    });

    it('keeps balances equal to the sum of their movements after confirming and cancelling', function () {
        $this->actingAs(userWithRole(Role::Sales));
        foreach ([[$this->shirt, 4], [$this->shirt, 5], [$this->cap, 1]] as [$variant, $quantity]) {
            $order = placeOrder($this->warehouse, [[$variant, $quantity]]);
            $this->postJson("/api/v1/orders/$order->id/confirm")->assertOk();
        }
        $this->postJson('/api/v1/orders/'.Order::query()->orderBy('id')->firstOrFail()->id.'/cancel')->assertOk();

        foreach (StockBalance::query()->get() as $balance) {
            $movements = StockMovement::query()->where(['variant_id' => $balance->variant_id, 'warehouse_id' => $balance->warehouse_id]);
            expect([$balance->on_hand, $balance->reserved])->toBe([(int) $movements->clone()->sum('on_hand_delta'), (int) $movements->clone()->sum('reserved_delta')]);
        }
        expect(stockOf($this->shirt, $this->warehouse))->toBe([10, 5]);
    });

    it('refuses a reason that is too long', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson("/api/v1/orders/$order->id/cancel", ['note' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    });

    it('is refused to Roles that may not cancel Orders', function (Role $role) {
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole($role))->postJson("/api/v1/orders/$order->id/cancel")->assertForbidden();
    })->with([Role::Inventory, Role::Support, Role::Finance, Role::Viewer]);
});
