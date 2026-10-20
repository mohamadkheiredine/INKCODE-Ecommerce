<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ListRequest;
use App\Models\Warehouse;

/**
 * The Warehouses list: search by name or code, filter by `status` (`active` or
 * `inactive`), sort by name, code or date.
 */
class ListWarehousesRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Warehouse::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'code', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filters(): array
    {
        return ['status' => ['string', 'in:active,inactive']];
    }
}
