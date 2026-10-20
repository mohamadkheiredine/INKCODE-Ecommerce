<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Enums\StockMovementType;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Stock movement history, newest first: search by SKU; filter by `variant`, `warehouse`,
 * `type` and the days `from` / `to` (inclusive).
 */
class ListStockMovementsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::InventoryView->value) ?? false;
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
            'variant' => ['integer'],
            'warehouse' => ['integer'],
            'type' => [Rule::enum(StockMovementType::class)],
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d', 'after_or_equal:filter.from'],
        ];
    }
}
