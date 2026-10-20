<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Http\Requests\ListRequest;

/**
 * Stock levels: search by SKU or Product name; filter by `warehouse`, `product`,
 * `category` (a top-level Category includes its children) and `low_stock`; sort by SKU,
 * Product, On-hand, Available or Stock value.
 */
class ListStockLevelsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::InventoryView->value) ?? false;
    }

    protected function sorts(): array
    {
        return ['sku', 'product_name', 'on_hand', 'available', 'stock_value'];
    }

    protected function defaultSort(): string
    {
        return 'product_name';
    }

    protected function filters(): array
    {
        return [
            'warehouse' => ['integer'],
            'product' => ['integer'],
            'category' => ['integer'],
            'low_stock' => ['boolean'],
        ];
    }
}
