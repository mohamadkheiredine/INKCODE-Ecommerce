<?php

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\Stock;
use App\Services\StockChange;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Every balance must equal the sum of its Stock movements, and each movement's "after"
 * values must continue the previous one's.
 */
function expectLedgerToMatchBalances(): void
{
    foreach (StockBalance::query()->get() as $balance) {
        $movements = StockMovement::query()
            ->where(['variant_id' => $balance->variant_id, 'warehouse_id' => $balance->warehouse_id])
            ->orderBy('id')
            ->get();

        [$onHand, $reserved] = [0, 0];
        foreach ($movements as $movement) {
            $onHand += $movement->on_hand_delta;
            $reserved += $movement->reserved_delta;
            expect([$movement->on_hand_after, $movement->reserved_after])->toBe([$onHand, $reserved]);
        }

        expect([$balance->on_hand, $balance->reserved])->toBe([$onHand, $reserved]);
    }
}

it('keeps every balance equal to the sum of its movements after a mixed sequence, refusals included', function () {
    [$beirut, $tripoli] = Warehouse::factory()->count(2)->create();
    [$tee, $mug] = Variant::factory()->count(2)->create();
    $stock = app(Stock::class);
    $source = receiveStock($beirut, [[$tee, 10, '5.00'], [$mug, 4, '2.00']]);
    receiveStock($tripoli, [[$tee, 3, '6.00']]);

    $stock->move(StockMovementType::Receipt, $source, [
        new StockChange($tee->id, $beirut->id, reservedDelta: 6),
        new StockChange($mug->id, $tripoli->id, onHandDelta: 2),
    ]);
    $stock->move(StockMovementType::Receipt, $source, [new StockChange($tee->id, $beirut->id, onHandDelta: -2, reservedDelta: -2)]);

    $refusals = [
        'reserving more than is available' => [new StockChange($tee->id, $beirut->id, reservedDelta: 5)],
        'releasing more than is reserved' => [new StockChange($tee->id, $beirut->id, reservedDelta: -5)],
        'taking more than is on hand' => [new StockChange($mug->id, $beirut->id, onHandDelta: -5)],
        'leaving reserved units without stock' => [new StockChange($tee->id, $beirut->id, onHandDelta: -5)],
        // All or nothing: the first change is valid but must not survive the second.
        'a valid change with an invalid one' => [
            new StockChange($tee->id, $tripoli->id, onHandDelta: -1),
            new StockChange($mug->id, $tripoli->id, onHandDelta: -3),
        ],
    ];
    foreach ($refusals as $case => $changes) {
        expect(fn () => $stock->move(StockMovementType::Receipt, $source, $changes))
            ->toThrow(BusinessRuleViolation::class, 'Not enough stock', $case);
    }

    expectLedgerToMatchBalances();
    expect(StockBalance::query()->where(['variant_id' => $tee->id, 'warehouse_id' => $beirut->id])->sole()->only('on_hand', 'reserved', 'available'))
        ->toBe(['on_hand' => 8, 'reserved' => 4, 'available' => 4])
        ->and(StockBalance::query()->where(['variant_id' => $tee->id, 'warehouse_id' => $tripoli->id])->sole()->on_hand)->toBe(3)
        ->and(StockMovement::query()->count())->toBe(6);
});

it('refuses units arriving in a deactivated Warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $variant = Variant::factory()->create();
    $source = receiveStock($warehouse, [[$variant, 1, '1.00']]);
    $warehouse->update(['is_active' => false]);

    expect(fn () => app(Stock::class)->move(StockMovementType::Receipt, $source, [new StockChange($variant->id, $warehouse->id, onHandDelta: 1)]))
        ->toThrow(BusinessRuleViolation::class, 'is deactivated');
});

it('lets the database itself refuse negative or over-reserved balances', function (array $values) {
    $balance = StockBalance::query()->create([
        'variant_id' => Variant::factory()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'on_hand' => 2,
        'reserved' => 1,
    ]);

    expect(fn () => DB::table('stock_balances')->where('id', $balance->id)->update($values))
        ->toThrow(QueryException::class, 'Check constraint');
})->with([
    'negative On-hand' => [['on_hand' => -1, 'reserved' => 0]],
    'negative Reserved' => [['reserved' => -1]],
    'Reserved above On-hand' => [['reserved' => 3]],
]);

it('never changes or deletes a Stock movement', function () {
    receiveStock(Warehouse::factory()->create(), [[Variant::factory()->create(), 1, '1.00']]);
    $movement = StockMovement::query()->sole();

    expect(fn () => $movement->update(['on_hand_delta' => 5]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});
