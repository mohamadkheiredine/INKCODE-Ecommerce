<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProductStatus;
use App\Http\Requests\ListRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * The Products list: search by name or SKU; filter by Category (a top-level Category
 * includes its children), Brand, status and price range; sort by name, price or date.
 */
class ListProductsRequest extends ListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    protected function sorts(): array
    {
        return ['name', 'price', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filters(): array
    {
        return [
            'category_id' => ['integer'],
            'brand_id' => ['integer'],
            'status' => ['string', Rule::enum(ProductStatus::class)],
            'price_min' => ['decimal:0,2', 'min:0'],
            'price_max' => ['decimal:0,2', 'min:0'],
        ];
    }
}
