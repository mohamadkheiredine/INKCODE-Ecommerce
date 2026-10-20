<?php

use App\Models\Receipt;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Models\Warehouse;
use Symfony\Component\Process\Process;

/*
 * Real concurrency: separate PHP processes, each with its own database connection, run
 * at the same moment against committed rows. Without the Variant row locks, the
 * re-weighting reads would interleave and lose updates.
 */

afterEach(function () {
    // Leaves the database empty and seeded, as the next RefreshDatabase test expects it.
    $this->truncateDatabaseTables();
});

it('re-weights the Average cost exactly when Receipts for one Variant arrive at the same moment', function () {
    [$beirut, $tripoli] = Warehouse::factory()->count(2)->create();
    $variant = Variant::factory()->create(['average_cost' => '0']);
    $startAt = microtime(true) + 5;

    $workers = collect([[$beirut, '4.00'], [$tripoli, '6.00'], [$beirut, '8.00'], [$tripoli, '10.00']])
        ->map(function (array $receipt) use ($startAt, $variant): Process {
            [$warehouse, $unitCost] = $receipt;
            $worker = new Process(
                [PHP_BINARY, __DIR__.'/receive-worker.php', (string) $startAt, (string) $warehouse->id, (string) $variant->id, '5', $unitCost],
                base_path(),
                ['APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database')],
                timeout: 60,
            );
            $worker->start();

            return $worker;
        });

    foreach ($workers as $worker) {
        $worker->wait();
        expect($worker->getOutput())->toBe('ok', $worker->getErrorOutput());
    }

    // (5 × 4 + 5 × 6 + 5 × 8 + 5 × 10) ÷ 20, in whatever order the Receipts committed.
    expect($variant->fresh()?->average_cost)->toBe('7.0000')
        ->and(Receipt::query()->count())->toBe(4)
        ->and(StockBalance::query()->orderBy('warehouse_id')->pluck('on_hand')->all())->toBe([10, 10]);

    foreach ([$beirut, $tripoli] as $warehouse) {
        expect(StockMovement::query()->where('warehouse_id', $warehouse->id)->orderBy('id')->pluck('on_hand_after')->all())->toBe([5, 10]);
    }
});
