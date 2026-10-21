<?php

namespace App\Http\Requests\Returns;

use App\Enums\Permission;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Returns: search the Return number, Order number or Customer name; filter by status and
 * reason; sort by request date or number; newest first by default.
 */
class ListReturnsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::ReturnsView->value) ?? false;
    }

    protected function sorts(): array
    {
        return ['requested_at', 'number'];
    }

    protected function defaultSort(): string
    {
        return '-requested_at';
    }

    protected function filters(): array
    {
        return [
            'status' => [Rule::enum(ReturnStatus::class)],
            'reason' => [Rule::enum(ReturnReason::class)],
        ];
    }
}
