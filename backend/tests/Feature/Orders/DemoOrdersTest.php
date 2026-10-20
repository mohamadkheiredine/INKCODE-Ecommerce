<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\StockBalance;
use App\Models\StockMovement;

it('seeds demo Orders whose reservations match their statuses and the movement ledger', function () {
    seedStore(demoMode: true);

    $statuses = Order::query()->pluck('status')->map(fn (OrderStatus $status): string => $status->value)->countBy()->sortKeys()->all();
    $reservedByConfirmedOrders = (int) Order::query()
        ->where('status', OrderStatus::Confirmed)
        ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
        ->sum('order_lines.quantity');

    expect($statuses)->toBe(['cancelled' => 4, 'confirmed' => 8, 'pending' => 4])
        ->and((int) StockBalance::query()->sum('reserved'))->toBe($reservedByConfirmedOrders)
        ->and(Order::query()->orderBy('id')->pluck('number')->first())->toEndWith('-000001')
        ->and(Order::query()->max('placed_at'))->toStartWith(now()->toDateString());

    foreach (StockBalance::query()->get() as $balance) {
        $movements = StockMovement::query()->where(['variant_id' => $balance->variant_id, 'warehouse_id' => $balance->warehouse_id]);
        expect([$balance->on_hand, $balance->reserved])->toBe([(int) $movements->clone()->sum('on_hand_delta'), (int) $movements->clone()->sum('reserved_delta')]);
    }

    // Seeding again changes nothing.
    seedStore(demoMode: true);
    expect(Order::query()->count())->toBe(16);
});
