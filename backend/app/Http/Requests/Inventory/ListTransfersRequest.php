<?php

namespace App\Http\Requests\Inventory;

use App\Enums\TransferStatus;
use App\Http\Requests\ListRequest;
use App\Models\Transfer;
use Illuminate\Validation\Rule;

/**
 * The Transfers list, newest first: search by number, filter by `status` (`in_transit`
 * shows what is on the way) and `warehouse` (either end).
 */
class ListTransfersRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Transfer::class) ?? false;
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
        return ['status' => [Rule::enum(TransferStatus::class)], 'warehouse' => ['integer']];
    }
}
