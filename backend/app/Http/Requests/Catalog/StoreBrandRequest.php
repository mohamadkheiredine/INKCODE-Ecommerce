<?php

namespace App\Http\Requests\Catalog;

use App\Models\Brand;
use App\Support\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Brand: a name and an optional slug (generated from the name when left out).
 * The logo has its own endpoint (BrandLogoController), so this stays plain JSON.
 */
class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:'.Slug::PATTERN, Rule::unique('brands', 'slug')->ignore($this->brand())],
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
     * The Brand being edited; none while creating one.
     */
    public function brand(): ?Brand
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand ? $brand : null;
    }

    /**
     * @return array{name: string, slug: string|null}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'slug' => $this->filled('slug') ? $this->string('slug')->toString() : null,
        ];
    }
}
