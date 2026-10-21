<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

it('seeds demo ledgers whose Payment statuses match them', function () {
    seedStore(demoMode: true);

    expect(Payment::query()->where('kind', 'refund')->count())->toBe(1)
        ->and(Payment::query()->where('status', 'failed')->count())->toBe(2)
        ->and(Payment::query()->distinct()->pluck('method')->map->value->sort()->values()->all())->toBe(['card', 'cash_on_delivery', 'manual']);

    foreach (Order::query()->with('payments')->get() as $order) {
        $succeeded = $order->payments->where('status.value', 'succeeded');
        $captured = $succeeded->where('kind.value', 'payment')->reduce(fn (string $sum, Payment $payment): string => bcadd($sum, $payment->amount, 2), '0.00');
        $refunded = $succeeded->where('kind.value', 'refund')->reduce(fn (string $sum, Payment $payment): string => bcadd($sum, $payment->amount, 2), '0.00');

        expect([$order->captured_amount, $order->refunded_amount])->toBe([$captured, $refunded])
            ->and($order->payment_status)->toBe(PaymentStatus::derive($captured, $refunded, $order->amountOwed()));
    }
});
