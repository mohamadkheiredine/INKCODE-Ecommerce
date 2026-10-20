<?php

use App\Enums\Role;
use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Models\Warehouse;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function receiptPayload(Warehouse $warehouse, Variant $variant, array $overrides = []): array
{
    return [
        'warehouse_id' => $warehouse->id,
        'supplier_reference' => 'PO-7781',
        'notes' => 'Autumn restock',
        'lines' => [['variant_id' => $variant->id, 'quantity' => 10, 'unit_cost' => '5.00']],
        ...$overrides,
    ];
}

describe('recording a Receipt', function () {
    it('adds to On-hand, appends a Stock movement and records the Receipt', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = Variant::factory()->create(['sku' => 'TEE-RED-M']);
        $user = userWithRole(Role::Inventory);

        $receipt = $this->actingAs($user)
            ->postJson('/api/v1/receipts', receiptPayload($warehouse, $variant))
            ->assertCreated()
            ->assertJsonPath('data.number', sprintf('RCV-%s-%06d', now()->format('Y'), Receipt::query()->sole()->id))
            ->assertJsonPath('data.lines.0.variant.sku', 'TEE-RED-M')
            ->assertJsonPath('data.lines.0.line_cost', '50.00')
            ->assertJsonPath('data.user.id', $user->id)
            ->json('data');

        $movement = StockMovement::query()->sole();
        expect(StockBalance::query()->sole()->only('variant_id', 'warehouse_id', 'on_hand', 'reserved', 'available'))
            ->toBe(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'on_hand' => 10, 'reserved' => 0, 'available' => 10])
            ->and($movement->only('on_hand_delta', 'reserved_delta', 'on_hand_after', 'user_id', 'source_type', 'source_id'))
            ->toBe(['on_hand_delta' => 10, 'reserved_delta' => 0, 'on_hand_after' => 10, 'user_id' => $user->id, 'source_type' => 'receipt', 'source_id' => $receipt['id']])
            ->and(auditTrailOf('receipt', $receipt['id'])->sole()->after)->toMatchArray([
                'number' => $receipt['number'],
                'supplier_reference' => 'PO-7781',
                'lines' => [['variant_id' => $variant->id, 'quantity' => 10, 'unit_cost' => '5.0000']],
            ]);
    });

    it('re-weights the Average cost across every Warehouse: 10 units at $5, then 10 at $7, cost $6', function () {
        [$beirut, $tripoli] = Warehouse::factory()->count(2)->create();
        $variant = Variant::factory()->create(['average_cost' => '10.0000']);
        $this->actingAs(userWithRole(Role::Inventory));

        $this->postJson('/api/v1/receipts', receiptPayload($beirut, $variant))->assertCreated();
        expect($variant->fresh()?->average_cost)->toBe('5.0000');

        $this->postJson('/api/v1/receipts', receiptPayload($tripoli, $variant, [
            'lines' => [['variant_id' => $variant->id, 'quantity' => 10, 'unit_cost' => '7.00']],
        ]))->assertCreated();

        expect($variant->fresh()?->average_cost)->toBe('6.0000')
            ->and(auditTrailOf('variant', $variant->id)->pluck('after.average_cost')->all())->toBe(['5.0000', '6.0000']);
    });

    it('rounds the re-weighted cost half up to four decimals', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = Variant::factory()->create();
        receiveStock($warehouse, [[$variant, 3, '1.00']]);
        receiveStock($warehouse, [[$variant, 3, '1.0001']]);

        expect($variant->fresh()?->average_cost)->toBe('1.0001');
    });

    it('refuses an inactive Warehouse, a repeated Variant, and bad quantities or costs', function () {
        $inactive = Warehouse::factory()->inactive()->create();
        $variant = Variant::factory()->create();

        $this->actingAs(userWithRole(Role::Inventory))
            ->postJson('/api/v1/receipts', receiptPayload($inactive, $variant, [
                'supplier_reference' => '',
                'lines' => [
                    ['variant_id' => $variant->id, 'quantity' => 0, 'unit_cost' => '-1'],
                    ['variant_id' => $variant->id, 'quantity' => 2, 'unit_cost' => '1.23456'],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'warehouse_id' => 'Choose an active Warehouse.',
                'supplier_reference',
                'lines.0.variant_id' => 'This Variant is already on the Receipt.',
                'lines.0.quantity',
                'lines.0.unit_cost',
                'lines.1.unit_cost',
            ]);

        $this->postJson('/api/v1/receipts', receiptPayload(Warehouse::factory()->create(), $variant, ['lines' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);

        expect(Receipt::query()->count())->toBe(0);
    });

    it('is refused to Roles that may only look at stock', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson('/api/v1/receipts', receiptPayload(Warehouse::factory()->create(), Variant::factory()->create()))
            ->assertForbidden();
    })->with([Role::Operations, Role::Sales, Role::Viewer]);
});

describe('retrying a Receipt with an Idempotency-Key', function () {
    it('records it once and answers the retry with the first answer', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = Variant::factory()->create();
        $payload = receiptPayload($warehouse, $variant);
        $this->actingAs(userWithRole(Role::Inventory));

        $first = $this->withHeader('Idempotency-Key', 'receipt-42')->postJson('/api/v1/receipts', $payload)->assertCreated();
        $retry = $this->withHeader('Idempotency-Key', 'receipt-42')->postJson('/api/v1/receipts', $payload)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        expect($retry->json())->toBe($first->json())
            ->and(Receipt::query()->count())->toBe(1)
            ->and(StockBalance::query()->sole()->on_hand)->toBe(10);
    });

    it('refuses the same key for a different request', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = Variant::factory()->create();
        $this->actingAs(userWithRole(Role::Inventory));

        $this->withHeader('Idempotency-Key', 'receipt-42')->postJson('/api/v1/receipts', receiptPayload($warehouse, $variant))->assertCreated();
        $this->withHeader('Idempotency-Key', 'receipt-42')
            ->postJson('/api/v1/receipts', receiptPayload($warehouse, $variant, ['supplier_reference' => 'PO-OTHER']))
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_key_reused');

        expect(Receipt::query()->count())->toBe(1);
    });

    it('keeps the key free after a refused request, so the corrected one can use it', function () {
        $warehouse = Warehouse::factory()->create();
        $variant = Variant::factory()->create();
        $this->actingAs(userWithRole(Role::Inventory));

        $this->withHeader('Idempotency-Key', 'receipt-42')
            ->postJson('/api/v1/receipts', receiptPayload($warehouse, $variant, ['supplier_reference' => '']))
            ->assertUnprocessable();
        $this->withHeader('Idempotency-Key', 'receipt-42')
            ->postJson('/api/v1/receipts', receiptPayload($warehouse, $variant))
            ->assertCreated();
    });
});

describe('listing and showing Receipts', function () {
    it('lists Receipts newest first with units and total cost, searchable and filtered by Warehouse', function () {
        [$beirut, $tripoli] = Warehouse::factory()->count(2)->create();
        [$tee, $cap] = Variant::factory()->count(2)->create();
        receiveStock($beirut, [[$tee, 10, '5.00'], [$cap, 3, '2.50']], 'PO-100');
        receiveStock($tripoli, [[$tee, 1, '7.00']], 'PO-200');

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/receipts')
            ->assertOk()
            ->assertJsonPath('data.0.supplier_reference', 'PO-200')
            ->assertJsonPath('data.1.units', 13)
            ->assertJsonPath('data.1.total_cost', '57.50');

        expect($this->getJson("/api/v1/receipts?filter[warehouse]={$beirut->id}")->json('data.*.supplier_reference'))->toBe(['PO-100'])
            ->and($this->getJson('/api/v1/receipts?search=PO-2')->json('data.*.supplier_reference'))->toBe(['PO-200']);
    });

    it('shows one Receipt with its lines, and refuses Roles that do not see stock', function () {
        $receipt = receiveStock(Warehouse::factory()->create(), [[Variant::factory()->create(['sku' => 'CAP-01']), 4, '3.25']]);

        $this->actingAs(userWithRole(Role::Finance))
            ->getJson("/api/v1/receipts/{$receipt->id}")
            ->assertOk()
            ->assertJsonPath('data.lines.0.variant.sku', 'CAP-01')
            ->assertJsonPath('data.lines.0.unit_cost', '3.2500');

        $this->actingAs(userWithRole(Role::Support))->getJson("/api/v1/receipts/{$receipt->id}")->assertForbidden();
        $this->getJson('/api/v1/receipts')->assertForbidden();
    });
});
