<?php

namespace App\Http\Requests\Audit;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * The audit log: search the record label; filter by User, action, record type, one
 * record (with its type) and a range of days; newest first by default.
 */
class ListAuditEntriesRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AuditView->value) ?? false;
    }

    protected function sorts(): array
    {
        return ['created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filters(): array
    {
        return [
            'user' => ['integer', 'min:1'],
            'action' => [Rule::enum(AuditAction::class)],
            'record_type' => ['string', 'max:64', 'alpha_dash', 'required_with:filter.record'],
            'record' => ['integer', 'min:1'],
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d', ...($this->filled('filter.from') ? ['after_or_equal:filter.from'] : [])],
        ];
    }
}
