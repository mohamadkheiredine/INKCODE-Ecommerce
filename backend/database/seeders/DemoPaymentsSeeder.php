<?php

namespace Database\Seeders;

use App\Actions\Payments\IssueRefund;
use App\Actions\Payments\RecordPayment;
use App\Enums\CardTestOutcome;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Payment ledgers for the demo Orders, recorded by the demo Finance User through the same
 * actions as the API, so every Payment status matches its ledger: Cash on delivery and
 * Manual payments, approved Card charges (some after a declined attempt), Manual deposits
 * on Pending Orders and a goodwill Refund. Cancelled Orders were never paid. Deterministic,
 * and recorded only once (skipped when the ledger has rows).
 */
class DemoPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        if (Payment::query()->exists()) {
            return;
        }

        Auth::setUser(User::query()->where('email', 'finance@inkcode.test')->firstOrFail());
        try {
            $orders = Order::query()->where('status', '!=', OrderStatus::Cancelled)->orderBy('id')->get();
            foreach ($orders->values() as $index => $order) {
                Carbon::setTestNow($order->placed_at->copy()->addHours(3)->min(Carbon::now()));
                $this->pay($order, $index);
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    private function pay(Order $order, int $index): void
    {
        $record = app(RecordPayment::class);

        match ($index % 6) {
            0 => $record->handle($order, PaymentMethod::CashOnDelivery, $order->total, null, 'Collected by the courier', null),
            1 => $record->handle($order, PaymentMethod::Manual, $order->total, null, 'Bank transfer', null),
            2 => $record->handle($order, PaymentMethod::Card, $order->total, CardTestOutcome::Approve, null, null),
            3 => (function () use ($record, $order): void {
                $record->handle($order, PaymentMethod::Card, $order->total, CardTestOutcome::Decline, null, null);
                Carbon::setTestNow(Carbon::now()->addMinutes(20));
                $record->handle($order, PaymentMethod::Card, $order->total, CardTestOutcome::Approve, 'Second attempt approved', null);
            })(),
            4 => $record->handle($order, PaymentMethod::Manual, bcdiv($order->total, '2', 2), null, 'Deposit in cash', null),
            default => null,
        };

        if ($index === 1) {
            // The second Order is weeks old, so a day later is still in the past.
            Carbon::setTestNow(Carbon::now()->addDay());
            app(IssueRefund::class)->handle($order, PaymentMethod::Manual, '5.00', 'Goodwill gesture for a late delivery', null);
        }
    }
}
