<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProductBulkAction;
use App\Http\Requests\ListRequest;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One action (activate, archive or delete) on up to a page of Products.
 */
class ProductBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', Product::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(ProductBulkAction::class)],
            'product_ids' => ['required', 'array', 'max:'.ListRequest::MAX_PER_PAGE],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
        ];
    }

    public function action(): ProductBulkAction
    {
        return ProductBulkAction::from($this->string('action')->toString());
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_map(intval(...), array_values((array) $this->validated('product_ids')));
    }
}
