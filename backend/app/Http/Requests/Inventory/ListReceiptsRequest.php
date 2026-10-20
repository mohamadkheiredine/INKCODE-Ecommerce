<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ListRequest;
use App\Models\Receipt;

/**
 * The Receipts list, newest first: search by number or supplier reference, filter by
 * `warehouse`.
 */
class ListReceiptsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Receipt::class) ?? false;
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
        return ['warehouse' => ['integer']];
    }
}
