<?php

namespace App\Http\Requests\Payments;

use App\Enums\Permission;
use App\Http\Requests\ListRequest;

/**
 * Reconciliation: search the Order number; filter by `balance` (`due`: the ledger holds
 * less than the Order owes, `over`: more); sort by date placed, total or difference;
 * newest first by default.
 */
class ListReconciliationRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::PaymentsView->value) ?? false;
    }

    protected function sorts(): array
    {
        return ['placed_at', 'total', 'difference'];
    }

    protected function defaultSort(): string
    {
        return '-placed_at';
    }

    protected function filters(): array
    {
        return ['balance' => ['in:due,over']];
    }
}
