<?php

use App\Enums\Role;
use App\Models\Variant;
use App\Models\Warehouse;

describe('listing Warehouses', function () {
    it('lists Warehouses with their units on hand, searchable and filtered by status', function () {
        $beirut = Warehouse::factory()->create(['name' => 'Beirut Central', 'code' => 'BEY-01']);
        Warehouse::factory()->inactive()->create(['name' => 'Old Depot', 'code' => 'OLD']);
        receiveStock($beirut, [[Variant::factory()->create(), 7, '2.00']]);

        $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/warehouses')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.code', 'BEY-01')
            ->assertJsonPath('data.0.on_hand', 7)
            ->assertJsonPath('data.1.on_hand', 0);

        expect($this->getJson('/api/v1/warehouses?filter[status]=inactive')->json('data.*.name'))->toBe(['Old Depot'])
            ->and($this->getJson('/api/v1/warehouses?search=bey')->json('data.*.name'))->toBe(['Beirut Central']);
    });

    it('is refused to Roles that do not see stock', function () {
        $this->actingAs(userWithRole(Role::Support))->getJson('/api/v1/warehouses')->assertForbidden();
    });
});

describe('creating and editing a Warehouse', function () {
    it('creates an active Warehouse with its code in capitals and records it', function () {
        $id = $this->actingAs(userWithRole(Role::Inventory))
            ->postJson('/api/v1/warehouses', ['name' => 'Tripoli North', 'code' => 'tri-02', 'address' => 'Port Road, Tripoli'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TRI-02')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        expect(auditTrailOf('warehouse', $id)->sole()->after)
            ->toEqual(['name' => 'Tripoli North', 'code' => 'TRI-02', 'address' => 'Port Road, Tripoli', 'is_active' => true]);
    });

    it('refuses a taken name or code, a malformed code and a missing address', function () {
        Warehouse::factory()->create(['name' => 'Beirut Central', 'code' => 'BEY-01']);

        $this->actingAs(userWithRole(Role::Inventory))
            ->postJson('/api/v1/warehouses', ['name' => 'Beirut Central', 'code' => 'bey-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'already been taken', 'code' => 'already been taken', 'address']);

        $this->postJson('/api/v1/warehouses', ['name' => 'Saida', 'code' => 'SAIDA SOUTH', 'address' => 'Saida'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Use 2 to 16 capital letters, digits or dashes for the code.']);
    });

    it('deactivates an empty Warehouse and records the change', function () {
        $warehouse = Warehouse::factory()->create(['name' => 'Saida', 'code' => 'SAI', 'address' => 'Saida']);

        $this->actingAs(userWithRole(Role::Admin))
            ->putJson("/api/v1/warehouses/{$warehouse->id}", ['name' => 'Saida', 'code' => 'SAI', 'address' => 'Saida', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect(auditTrailOf('warehouse', $warehouse->id)->sole()->after)->toBe(['is_active' => false]);
    });

    it('refuses to deactivate a Warehouse that still holds stock', function () {
        $warehouse = Warehouse::factory()->create(['name' => 'Saida', 'code' => 'SAI', 'address' => 'Saida']);
        receiveStock($warehouse, [[Variant::factory()->create(), 1, '1.00']]);

        $this->actingAs(userWithRole(Role::Inventory))
            ->putJson("/api/v1/warehouses/{$warehouse->id}", ['name' => 'Saida', 'code' => 'SAI', 'address' => 'Saida', 'is_active' => false])
            ->assertConflict()
            ->assertJsonPath('code', 'warehouse_holds_stock');

        expect($warehouse->fresh()?->is_active)->toBeTrue();
    });

    it('refuses Roles that see stock but may not manage Warehouses', function () {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(userWithRole(Role::Operations))
            ->postJson('/api/v1/warehouses', ['name' => 'Zahle', 'code' => 'ZAH', 'address' => 'Zahle'])
            ->assertForbidden();
        $this->putJson("/api/v1/warehouses/{$warehouse->id}", ['name' => 'Zahle', 'code' => 'ZAH', 'address' => 'Zahle'])
            ->assertForbidden();
    });
});
