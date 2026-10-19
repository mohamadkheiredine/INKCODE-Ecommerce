<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Product's details. Prices are decimal strings with at most two decimals, entered
 * without VAT. The slug is generated from the name when left empty. Images and Variants
 * have their own endpoints.
 */
class StoreProductRequest extends FormRequest
{
    public const string MAX_PRICE = '99999999.99';

    public const int MAX_TAGS = 20;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:'.Slug::PATTERN, Rule::unique('products', 'slug')->ignore($this->product())],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'base_price' => ['required', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],
            'taxable' => ['required', 'boolean'],
            'tags' => ['present', 'array', 'max:'.self::MAX_TAGS],
            'tags.*' => ['string', 'distinct:ignore_case', 'max:40'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => __('catalog.slug_format')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => __('catalog.products.category'),
            'brand_id' => __('catalog.products.brand'),
            'base_price' => __('catalog.products.base_price'),
            'tags.*' => __('catalog.products.tag'),
        ];
    }

    /**
     * The Product being edited; none while creating one.
     */
    public function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array{name: string, slug: string|null, description: string|null, category_id: int, brand_id: int|null, base_price: string, taxable: bool, tags: list<string>, seo_title: string|null, seo_description: string|null, status: ProductStatus}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'slug' => $this->filled('slug') ? $this->string('slug')->toString() : null,
            'description' => $this->optionalText('description'),
            'category_id' => $this->integer('category_id'),
            'brand_id' => $this->filled('brand_id') ? $this->integer('brand_id') : null,
            'base_price' => (string) $this->validated('base_price'),
            'taxable' => $this->boolean('taxable'),
            'tags' => array_values(array_filter(array_map(
                fn (mixed $tag): string => trim((string) $tag),
                (array) $this->validated('tags'),
            ), fn (string $tag): bool => $tag !== '')),
            'seo_title' => $this->optionalText('seo_title'),
            'seo_description' => $this->optionalText('seo_description'),
            'status' => $this->enum('status', ProductStatus::class) ?? ProductStatus::Draft,
        ];
    }

    private function optionalText(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
