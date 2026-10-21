<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Variant;
use App\Models\Warehouse;
use Symfony\Component\Process\Process;

/*
 * Real concurrency: separate PHP processes confirm Orders that use the same Coupon at the
 * same moment, against committed rows. The Coupon's row lock makes them count its uses one
 * after another, so its usage limit holds.
 */

afterEach(function () {
    $this->truncateDatabaseTables();
});

it('confirms exactly as many Orders as the Coupon’s usage limit when they confirm at the same moment', function () {
    $warehouse = Warehouse::factory()->create();
    $variant = Variant::factory()->create(['price' => '10.00']);
    receiveStock($warehouse, [[$variant, 50, '4.00']]);
    Coupon::factory()->create(['code' => 'RACE', 'usage_limit' => 3]);
    $orders = collect(range(1, 8))->map(fn (): Order => placeOrder($warehouse, [[$variant, 1]], couponCode: 'RACE'));
    $startAt = microtime(true) + 5;

    $workers = $orders->map(function (Order $order) use ($startAt): Process {
        $worker = new Process(
            [PHP_BINARY, 'artisan', 'demo:race-worker', (string) $order->id, (string) $startAt],
            base_path(),
            ['APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.mysql.database')],
            timeout: 60,
        );
        $worker->start();

        return $worker;
    });

    $outcomes = $workers->map(function (Process $worker): string {
        $worker->wait();

        return trim($worker->getOutput()) ?: 'error: '.$worker->getErrorOutput();
    })->countBy()->sortKeys()->all();

    expect($outcomes)->toBe(['confirmed' => 3, 'coupon_invalid' => 5])
        ->and(Order::query()->where('status', OrderStatus::Confirmed)->count())->toBe(3)
        ->and(CouponRedemption::query()->whereNull('released_at')->count())->toBe(3);
});
