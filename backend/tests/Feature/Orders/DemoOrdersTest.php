<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

it('seeds demo Orders and Shipments whose reservations match their statuses and the movement ledger', function () {
    seedStore(demoMode: true);

    $statuses = Order::query()->pluck('status')->map(fn (OrderStatus $status): string => $status->value)->countBy()->sortKeys()->all();
    $reservedByOpenOrders = (int) Order::query()
        ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::ReadyForShipping])
        ->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
        ->sum(DB::raw('order_lines.quantity - order_lines.deducted_quantity'));

    expect($statuses)->toBe(['cancelled' => 4, 'confirmed' => 2, 'delivered' => 12, 'pending' => 4, 'preparing' => 1, 'ready_for_shipping' => 2, 'shipped' => 1])
        ->and((int) StockBalance::query()->sum('reserved'))->toBe($reservedByOpenOrders)
        ->and(Shipment::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(Shipment::query()->where('status', 'delivered')->count())->toBeGreaterThanOrEqual(2)
        ->and(Order::query()->orderBy('id')->pluck('number')->first())->toEndWith('-000001')
        ->and(Order::query()->max('placed_at'))->toStartWith(now()->toDateString());

    foreach (StockBalance::query()->get() as $balance) {
        $movements = StockMovement::query()->where(['variant_id' => $balance->variant_id, 'warehouse_id' => $balance->warehouse_id]);
        expect([$balance->on_hand, $balance->reserved])->toBe([(int) $movements->clone()->sum('on_hand_delta'), (int) $movements->clone()->sum('reserved_delta')]);
    }

    // Seeding again changes nothing.
    seedStore(demoMode: true);
    expect(Order::query()->count())->toBe(26);
});
