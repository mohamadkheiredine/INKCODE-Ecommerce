<?php

use App\Enums\Role;
use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;

it('seeds Warehouses and stock received at different costs, consistent with the ledger, idempotently', function () {
    seedStore(demoMode: true);
    seedStore(demoMode: true);

    $this->actingAs(userWithRole(Role::Inventory));
    $this->getJson('/api/v1/warehouses?filter[status]=active')->assertJsonPath('meta.total', 3);
    $this->getJson('/api/v1/warehouses?filter[status]=inactive')->assertJsonPath('meta.total', 1);
    expect($this->getJson('/api/v1/stock-levels?filter[low_stock]=1')->json('meta.total'))->toBeGreaterThan(0)
        ->and($this->getJson('/api/v1/stock-levels')->json('meta.totals.stock_value'))->not->toBe('0.00');

    // Received three times at rising costs, so its Average cost lies between the first and last.
    $tee = Variant::query()->where('sku', 'like', 'CLASSIC-COTTON-TEE-BLK-S%')->sole();
    expect(Receipt::query()->count())->toBe(4)
        ->and((float) $tee->average_cost)->toBeGreaterThan(7.96)->toBeLessThan(10.34)
        ->and(StockBalance::query()->sum('on_hand'))->toEqual(StockMovement::query()->sum('on_hand_delta'));
});
