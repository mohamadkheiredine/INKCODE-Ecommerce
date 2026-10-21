<?php

namespace App\Actions\Returns;

use App\Enums\OrderStatus;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderReturn;
use App\Models\User;
use App\Notifications\ReturnRequested;
use App\Services\AuditTrail;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Requests a Return for some units of a Delivered Order's lines, within the Return window.
 * Nothing changes in stock or money yet. Active Support Users are notified.
 */
final class RequestReturn
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array{reason: ReturnReason, note: string|null, lines: list<array{line_id: int, quantity: int}>}  $values  each line once
     *
     * @throws BusinessRuleViolation 409 `order_not_delivered`, 409 `return_window_closed`,
     *                               409 `return_exceeds_delivered` (with `details.lines`)
     */
    public function handle(Order $order, array $values, User $user): OrderReturn
    {
        return DB::transaction(function () use ($order, $values, $user): OrderReturn {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Delivered) {
                throw new BusinessRuleViolation('order_not_delivered', __('returns.order_not_delivered', ['number' => $order->number]));
            }

            $windowEndsAt = $order->returnWindowEndsAt($this->settings->returnWindowDays());
            if ($windowEndsAt === null || Carbon::now()->greaterThan($windowEndsAt)) {
                throw new BusinessRuleViolation('return_window_closed', __('returns.window_closed', [
                    'number' => $order->number,
                    'days' => $this->settings->returnWindowDays(),
                ]));
            }

            $lines = $order->lines()->with('returnLines.orderReturn:id,status')->get()->keyBy('id');
            $excess = [];
            foreach ($values['lines'] as $requested) {
                /** @var OrderLine $line */
                $line = $lines[$requested['line_id']];
                if ($requested['quantity'] > $line->returnableQuantity()) {
                    $excess[] = ['line_id' => $line->id, 'sku' => $line->sku, 'requested' => $requested['quantity'], 'returnable' => $line->returnableQuantity()];
                }
            }
            if ($excess !== []) {
                throw new BusinessRuleViolation('return_exceeds_delivered', __('returns.exceeds_delivered', [
                    'lines' => implode(', ', array_map(fn (array $line): string => __('returns.exceeds_line', $line), $excess)),
                ]), details: ['lines' => $excess]);
            }

            $now = Carbon::now();
            $return = OrderReturn::query()->create([
                'number' => $this->nextNumber($now),
                'order_id' => $order->id,
                'status' => ReturnStatus::Requested,
                'reason' => $values['reason'],
                'note' => $values['note'],
                'user_id' => $user->id,
                'requested_at' => $now,
            ]);
            $return->lines()->createMany(array_map(
                fn (array $line): array => ['order_line_id' => $line['line_id'], 'quantity' => $line['quantity']],
                $values['lines'],
            ));
            $return->load('lines');
            $this->auditTrail->created($return);

            Notification::send(
                User::query()
                    ->where('is_active', true)
                    ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Support->value))
                    ->get(),
                new ReturnRequested($return->id, $return->number, $order->number),
            );

            return $return;
        });
    }

    /**
     * RET-<year>-<six digits>, from a per-year counter locked until the Return commits.
     */
    private function nextNumber(Carbon $now): string
    {
        $year = (int) $now->format('Y');
        DB::table('return_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $last = (int) DB::table('return_number_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
        DB::table('return_number_sequences')->where('year', $year)->update(['last_number' => $last + 1]);

        return sprintf('RET-%d-%06d', $year, $last + 1);
    }
}
