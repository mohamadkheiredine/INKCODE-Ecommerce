<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Models\Variant;
use App\Support\Sku;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * New values for some of a Product's Variants, saved together: SKU, price, Average cost
 * and Low-stock threshold (empty follows the store default). A SKU must be unique among
 * all Variants; Variants in the same save may swap SKUs.
 */
class UpdateVariantsRequest extends FormRequest
{
    public const int MAX_VARIANTS = 500;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->product()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variants' => ['required', 'array', 'max:'.self::MAX_VARIANTS],
            'variants.*.id' => ['required', 'integer', 'distinct', Rule::exists('variants', 'id')->where('product_id', $this->product()->id)],
            'variants.*.sku' => ['required', 'string', 'max:'.Sku::MAX_LENGTH, 'regex:'.Sku::PATTERN, 'distinct'],
            'variants.*.price' => ['required', 'decimal:0,2', 'min:0', 'max:'.StoreProductRequest::MAX_PRICE],
            'variants.*.average_cost' => ['required', 'decimal:0,4', 'min:0', 'max:'.StoreProductRequest::MAX_PRICE],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * SKUs used by Variants outside this save are taken.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = collect($this->variantValues());
            $taken = Variant::query()
                ->whereIn('sku', $rows->pluck('sku'))
                ->whereKeyNot($rows->pluck('id'))
                ->pluck('sku')
                ->flip();

            foreach ($rows as $index => $row) {
                if ($taken->has($row['sku'])) {
                    $validator->errors()->add("variants.$index.sku", __('catalog.variants.sku_taken', ['sku' => $row['sku']]));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.*.sku.regex' => __('catalog.code_format'),
            'variants.*.sku.distinct' => __('catalog.variants.sku_repeated'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'variants.*.id' => __('catalog.variants.variant'),
            'variants.*.sku' => 'SKU',
            'variants.*.price' => __('catalog.variants.price'),
            'variants.*.average_cost' => __('catalog.variants.average_cost'),
            'variants.*.low_stock_threshold' => __('catalog.variants.low_stock_threshold'),
        ];
    }

    public function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }

    /**
     * @return list<array{id: int, sku: string, price: string, average_cost: string, low_stock_threshold: int|null}>
     */
    public function variantValues(): array
    {
        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'sku' => (string) $row['sku'],
            'price' => (string) $row['price'],
            'average_cost' => (string) $row['average_cost'],
            'low_stock_threshold' => isset($row['low_stock_threshold']) ? (int) $row['low_stock_threshold'] : null,
        ], array_values((array) $this->input('variants')));
    }
}
