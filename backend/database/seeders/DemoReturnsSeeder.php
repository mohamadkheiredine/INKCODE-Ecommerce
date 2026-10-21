<?php

namespace Database\Seeders;

use App\Actions\Returns\DecideRestock;
use App\Actions\Returns\MoveReturn;
use App\Actions\Returns\RefundReturn;
use App\Actions\Returns\RequestReturn;
use App\Enums\OrderStatus;
use App\Enums\RestockDecision;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Returns on the demo Delivered Orders, one delivered unit each, a day after delivery, through the same actions as the API, so stock and Payment ledgers stay
 * consistent: one Completed with a restock and its exact Refund (capped by what was
 * paid), one Received with a Write-off still to refund, one Approved, one Rejected and
 * one Requested. Deterministic, and recorded only once (skipped when Returns exist).
 */
class DemoReturnsSeeder extends Seeder
{
    private const array REASONS = [ReturnReason::Damaged, ReturnReason::WrongItem, ReturnReason::NotAsDescribed, ReturnReason::ChangedMind, ReturnReason::Other];

    private ?Carbon $realNow = null;

    public function run(): void
    {
        if (OrderReturn::query()->exists()) {
            return;
        }

        try {
            // One delivered unit per Return, spread over the Delivered Orders' lines.
            $units = Order::query()->where('status', OrderStatus::Delivered)->orderBy('id')->with('lines')->get()
                ->flatMap(fn (Order $order) => $order->lines->flatMap(fn (OrderLine $line) => array_fill(0, $line->shipped_quantity, [$order, $line])))
                ->take(count(self::REASONS))
                ->values();
            foreach ($units as $index => [$order, $line]) {
                $deliveredAt = $order->deliveredAt() ?? Carbon::now();
                $this->at($deliveredAt->copy()->addDay()->addHours(2 * $index));
                $this->act($index, $order, $line);
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    private function act(int $index, Order $order, OrderLine $line): void
    {
        $return = $this->as('support', fn (User $user): OrderReturn => app(RequestReturn::class)->handle($order, [
            'reason' => self::REASONS[$index],
            'note' => $index === 0 ? 'The seam tore on first wear' : null,
            'lines' => [['line_id' => $line->id, 'quantity' => 1]],
        ], $user));

        if ($index === 4) {
            return;
        }

        $this->later();
        if ($index === 3) {
            $this->as('support', fn (): OrderReturn => app(MoveReturn::class)->handle($return, ReturnStatus::Rejected, 'Used item, outside the return policy'));

            return;
        }
        $this->as('support', fn (): OrderReturn => app(MoveReturn::class)->handle($return, ReturnStatus::Approved));
        if ($index === 2) {
            return;
        }

        $this->later();
        $this->as('inventory', fn (): OrderReturn => app(MoveReturn::class)->handle($return, ReturnStatus::Received));
        $decision = $index === 0 ? RestockDecision::Restock : RestockDecision::WriteOff;
        $this->as('inventory', fn (): OrderReturn => app(DecideRestock::class)->handle($return, [$return->lines()->firstOrFail()->id => $decision]));
        if ($index === 1) {
            return;
        }

        $this->later();
        $return->load(['lines.orderLine', 'order']);
        $suggested = $return->suggestedRefund(false);
        $refundable = $order->refresh()->netCaptured();
        $amount = bccomp($suggested, $refundable, 2) <= 0 ? $suggested : $refundable;
        $this->as('finance', fn (): OrderReturn => app(RefundReturn::class)->handle($return, $amount, false, null));
    }

    /**
     * @template T
     *
     * @param  callable(User): T  $step
     * @return T
     */
    private function as(string $role, callable $step): mixed
    {
        $user = User::query()->where('email', "$role@inkcode.test")->firstOrFail();
        Auth::setUser($user);

        return $step($user);
    }

    /** Moves the clock there, never past the real now. */
    private function at(Carbon $moment): void
    {
        $this->realNow ??= Carbon::now();
        Carbon::setTestNow($moment->copy()->min($this->realNow));
    }

    /** A day later, never past the real now. */
    private function later(): void
    {
        $this->at(Carbon::now()->addDay());
    }
}
