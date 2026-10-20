<?php

namespace App\Http\Requests\Catalog;

use App\Models\Attribute;
use App\Support\Sku;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An Attribute and its whole list of values, in display order. Each value has a name
 * and a code for generated SKUs ("Red", "RED"), both unique within the Attribute. When
 * editing, a value with an `id` is kept (and renamed), one without is new, and any value
 * left out is removed.
 */
class StoreAttributeRequest extends FormRequest
{
    public const int MAX_VALUES = 100;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Attribute::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attribute = $this->attributeBeingEdited();

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('attributes', 'name')->ignore($attribute)],
            'values' => ['present', 'array', 'max:'.self::MAX_VALUES],
            'values.*.id' => $attribute === null
                ? ['prohibited']
                : ['nullable', 'integer', 'distinct', Rule::exists('attribute_values', 'id')->where('attribute_id', $attribute->id)],
            'values.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'values.*.code' => ['required', 'string', 'max:20', 'regex:'.Sku::PATTERN, 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*.id.exists' => __('catalog.attributes.value_missing'),
            'values.*.name.distinct' => __('catalog.attributes.value_repeated'),
            'values.*.code.distinct' => __('catalog.attributes.value_repeated'),
            'values.*.code.regex' => __('catalog.code_format'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'values.*.id' => __('catalog.attributes.value'),
            'values.*.name' => __('catalog.attributes.value_name'),
            'values.*.code' => __('catalog.attributes.value_code'),
        ];
    }

    /**
     * The Attribute being edited; none while creating one. (FormRequest already has an
     * `attributes()` method, hence the longer name.)
     */
    public function attributeBeingEdited(): ?Attribute
    {
        $attribute = $this->route('attribute');

        return $attribute instanceof Attribute ? $attribute : null;
    }

    /**
     * @return array{name: string, values: list<array{id: int|null, name: string, code: string}>}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'values' => array_map(fn (array $value): array => [
                'id' => isset($value['id']) ? (int) $value['id'] : null,
                'name' => trim((string) $value['name']),
                'code' => (string) $value['code'],
            ], array_values((array) $this->validated('values'))),
        ];
    }
}
