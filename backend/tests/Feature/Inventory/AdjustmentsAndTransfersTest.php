<?php

use App\Enums\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;

function stockUp(Warehouse $warehouse, Variant $variant, int $quantity): void
{
    test()->postJson('/api/v1/receipts', [
        'warehouse_id' => $warehouse->id,
        'supplier_reference' => 'PO-1',
        'lines' => [['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost' => '1.00']],
    ])->assertCreated();
}

function balanceOf(Warehouse $warehouse, Variant $variant): ?StockBalance
{
    return StockBalance::query()->where(['warehouse_id' => $warehouse->id, 'variant_id' => $variant->id])->first();
}

beforeEach(function () {
    [$this->beirut, $this->tripoli] = Warehouse::factory()->count(2)->create();
    $this->variant = Variant::factory()->create(['low_stock_threshold' => 2]);
    $this->actingAs($this->user = userWithRole(Role::Inventory));
    stockUp($this->beirut, $this->variant, 10);
});

describe('Adjustments', function () {
    it('changes On-hand with a reason, records a movement and an audit entry', function () {
        $id = $this->postJson('/api/v1/adjustments', [
            'warehouse_id' => $this->beirut->id, 'variant_id' => $this->variant->id, 'quantity_delta' => -3, 'reason' => 'damaged',
        ])->assertCreated()->assertJsonPath('data.reason', 'damaged')->json('data.id');

        expect(balanceOf($this->beirut, $this->variant)?->on_hand)->toBe(7)
            ->and(StockMovement::query()->latest('id')->first()?->only('type', 'on_hand_delta', 'reason', 'source_type'))
            ->toMatchArray(['on_hand_delta' => -3, 'reason' => 'damaged', 'source_type' => 'adjustment'])
            ->and(auditTrailOf('adjustment', $id)->count())->toBe(1);
    });

    it('requires a known reason and a non-zero quantity', function () {
        $this->postJson('/api/v1/adjustments', ['warehouse_id' => $this->beirut->id, 'variant_id' => $this->variant->id, 'quantity_delta' => 0, 'reason' => 'stolen'])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity_delta', 'reason']);
    });

    it('never pushes On-hand below Reserved', function () {
        balanceOf($this->beirut, $this->variant)?->update(['reserved' => 8]);

        $this->postJson('/api/v1/adjustments', ['warehouse_id' => $this->beirut->id, 'variant_id' => $this->variant->id, 'quantity_delta' => -3, 'reason' => 'lost'])
            ->assertConflict()->assertJsonPath('code', 'insufficient_stock');
        expect(balanceOf($this->beirut, $this->variant)?->on_hand)->toBe(10);
    });

    it('refuses Roles without inventory.adjust', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson('/api/v1/adjustments', ['warehouse_id' => $this->beirut->id, 'variant_id' => $this->variant->id, 'quantity_delta' => 1, 'reason' => 'found'])
            ->assertForbidden();
    })->with([Role::Operations, Role::Sales, Role::Support, Role::Finance, Role::Viewer]);
});

describe('Transfers', function () {
    function draftTransfer(int $quantity): int
    {
        return test()->postJson('/api/v1/transfers', [
            'source_warehouse_id' => test()->beirut->id,
            'destination_warehouse_id' => test()->tripoli->id,
            'lines' => [['variant_id' => test()->variant->id, 'quantity' => $quantity]],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
    }

    it('sends out of the source, shows in transit, then receives into the destination', function () {
        $id = draftTransfer(4);
        expect(balanceOf($this->beirut, $this->variant)?->on_hand)->toBe(10);

        $this->postJson("/api/v1/transfers/$id/send")->assertOk()->assertJsonPath('data.status', 'in_transit');
        $this->getJson('/api/v1/transfers?filter[status]=in_transit')->assertJsonPath('data.0.units', 4);
        expect(balanceOf($this->beirut, $this->variant)?->on_hand)->toBe(6)
            ->and(balanceOf($this->tripoli, $this->variant))->toBeNull();

        $this->postJson("/api/v1/transfers/$id/receive")->assertOk()->assertJsonPath('data.status', 'received');
        expect(balanceOf($this->tripoli, $this->variant)?->on_hand)->toBe(4)
            ->and(StockMovement::query()->where('source_type', 'transfer')->pluck('type')->map->value->all())
            ->toBe(['transfer_out', 'transfer_in']);
    });

    it('checks Available when sending', function () {
        balanceOf($this->beirut, $this->variant)?->update(['reserved' => 7]);
        $id = draftTransfer(4);

        $this->postJson("/api/v1/transfers/$id/send")->assertConflict()->assertJsonPath('code', 'insufficient_stock');
        expect(Transfer::query()->find($id)?->status->value)->toBe('draft');
    });

    it('cancels only unsent Transfers, and never sends twice', function () {
        $draft = draftTransfer(1);
        $this->postJson("/api/v1/transfers/$draft/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/transfers/$draft/send")->assertConflict()->assertJsonPath('code', 'invalid_transition');

        $sent = draftTransfer(1);
        $this->postJson("/api/v1/transfers/$sent/send")->assertOk();
        $this->postJson("/api/v1/transfers/$sent/send")->assertConflict();
        $this->postJson("/api/v1/transfers/$sent/cancel")->assertConflict();
    });

    it('refuses Roles without inventory.transfer', function (Role $role) {
        $id = draftTransfer(1);
        $this->actingAs(userWithRole($role));

        $this->postJson("/api/v1/transfers/$id/send")->assertForbidden();
        $this->postJson('/api/v1/transfers', [])->assertForbidden();
    })->with([Role::Operations, Role::Sales, Role::Finance, Role::Viewer]);
});

describe('low-stock alerts', function () {
    it('notifies Inventory and Admin Users once until stock recovers above the threshold', function () {
        $admin = userWithRole(Role::Admin);
        $sales = userWithRole(Role::Sales);
        $adjust = fn (int $delta) => $this->postJson('/api/v1/adjustments', [
            'warehouse_id' => $this->beirut->id, 'variant_id' => $this->variant->id, 'quantity_delta' => $delta, 'reason' => 'count_correction',
        ])->assertCreated();
        $alerts = fn (User $user): int => $user->notifications()->where('type', 'low_stock')->count();

        $adjust(-8);
        $adjust(-1);
        expect($alerts($this->user))->toBe(1)->and($alerts($admin))->toBe(1)->and($alerts($sales))->toBe(0);

        $adjust(5);
        $adjust(-5);
        expect($alerts($this->user))->toBe(2);
    });
});
