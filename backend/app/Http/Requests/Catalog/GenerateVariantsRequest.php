<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Attribute values to generate a Product's Variants from (any number per
 * Attribute). An empty list makes the single Variant of a Product without Attributes.
 */
class GenerateVariantsRequest extends FormRequest
{
    public const int MAX_VALUES = 100;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attribute_value_ids' => ['present', 'array', 'max:'.self::MAX_VALUES],
            'attribute_value_ids.*' => ['integer', 'distinct', Rule::exists('attribute_values', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['attribute_value_ids.*' => __('catalog.variants.value')];
    }

    /**
     * @return list<int>
     */
    public function valueIds(): array
    {
        return array_map(intval(...), (array) $this->validated('attribute_value_ids'));
    }
}
