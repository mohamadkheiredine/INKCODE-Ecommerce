<?php

use App\Models\OrderReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;

it('seeds demo Returns in every status, with stock and Refunds that match', function () {
    seedStore(demoMode: true);

    expect(OrderReturn::query()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(['approved', 'completed', 'received', 'rejected', 'requested'])
        ->and(StockMovement::query()->where('type', 'return_restock')->count())->toBe(1);

    $completed = OrderReturn::query()->where('status', 'completed')->with('refund')->sole();
    expect($completed->refund?->amount ?? '0.00')->toBe($completed->refund_amount);

    foreach (StockBalance::query()->get() as $balance) {
        $movements = StockMovement::query()->where(['variant_id' => $balance->variant_id, 'warehouse_id' => $balance->warehouse_id]);
        expect((int) $movements->sum('on_hand_delta'))->toBe($balance->on_hand);
    }
});
