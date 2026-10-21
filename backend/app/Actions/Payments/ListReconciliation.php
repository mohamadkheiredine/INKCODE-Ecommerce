<?php

namespace App\Actions\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentOutcome;
use App\Models\Order;
use App\Models\Payment;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Reconciliation: the Orders whose ledger total differs from what they owe. The ledger
 * total is summed from the Payment ledger itself (successful payments minus Refunds), not
 * from the amounts kept on the Order, so the view also catches an Order whose kept totals
 * drifted. Each row has `amount_owed`, `ledger_total` and `difference` (ledger − owed).
 */
final class ListReconciliation
{
    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $ledger = Payment::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN payments.kind = 'payment' THEN payments.amount ELSE -payments.amount END), 0)")
            ->whereColumn('payments.order_id', 'orders.id')
            ->where('payments.status', PaymentOutcome::Succeeded->value)
            ->toBase();
        $ledgerSql = '('.$ledger->toSql().')';
        $owedSql = 'CASE WHEN orders.status = ? THEN 0 ELSE orders.total END';
        $bindings = [...$ledger->getBindings(), OrderStatus::Cancelled->value];

        $query = Order::query()
            ->with('customer:id,name')
            ->select('orders.*')
            ->selectRaw("$owedSql AS amount_owed", [OrderStatus::Cancelled->value])
            ->selectRaw("$ledgerSql AS ledger_total", $ledger->getBindings())
            ->selectRaw("$ledgerSql - ($owedSql) AS difference", $bindings);

        $comparison = match ($parameters->filter('balance')) {
            'due' => '<',
            'over' => '>',
            default => '<>',
        };
        $query->whereRaw("$ledgerSql $comparison ($owedSql)", $bindings);

        $parameters->search($query, ['number']);

        return $parameters->paginate($query);
    }
}
