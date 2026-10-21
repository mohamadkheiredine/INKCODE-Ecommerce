<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderAssigned;
use App\Services\AuditTrail;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

/**
 * Gives an Order its Assignee, or takes it away (null). The new Assignee is told through
 * the bell, unless they assigned the Order to themselves. Allowed in every status.
 */
final class AssignOrder
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Order $order, ?User $assignee): Order
    {
        return DB::transaction(function () use ($order, $assignee): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = $order->auditValues();

            if ($order->assignee_id === $assignee?->id) {
                return $order;
            }

            $order->update(['assignee_id' => $assignee?->id]);
            $this->auditTrail->updated($order, $before);

            $actorId = $this->auth->guard()->id();
            if ($assignee !== null && $assignee->id !== ($actorId === null ? null : (int) $actorId)) {
                $assignee->notify(new OrderAssigned($order->id, $order->number));
            }

            return $order;
        });
    }
}
