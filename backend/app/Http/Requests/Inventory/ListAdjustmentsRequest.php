<?php

namespace App\Http\Requests\Inventory;

use App\Enums\AdjustmentReason;
use App\Http\Requests\ListRequest;
use App\Models\Adjustment;
use Illuminate\Validation\Rule;

/**
 * The Adjustments list, newest first: search by number, filter by `warehouse` and `reason`.
 */
class ListAdjustmentsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Adjustment::class) ?? false;
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
        return ['warehouse' => ['integer'], 'reason' => [Rule::enum(AdjustmentReason::class)]];
    }
}
