<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOutcome;
use App\Enums\Permission;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Payment ledger rows of every Order: search the Order number or gateway reference;
 * filter by kind, Payment method, status and a range of days; sort by date or amount;
 * newest first by default.
 */
class ListPaymentsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::PaymentsView->value) ?? false;
    }

    protected function sorts(): array
    {
        return ['created_at', 'amount'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filters(): array
    {
        return [
            'kind' => [Rule::enum(PaymentKind::class)],
            'method' => [Rule::enum(PaymentMethod::class)],
            'status' => [Rule::enum(PaymentOutcome::class)],
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d', ...($this->filled('filter.from') ? ['after_or_equal:filter.from'] : [])],
        ];
    }
}
