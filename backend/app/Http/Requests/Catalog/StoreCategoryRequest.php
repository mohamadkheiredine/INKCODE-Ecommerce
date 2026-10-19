<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use App\Support\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Category: a name, an optional slug (generated from the name when left out),
 * an optional parent and an optional description. Whether the parent may hold children
 * is a business rule, checked under lock by CategoryHierarchy.
 */
class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:'.Slug::PATTERN, Rule::unique('categories', 'slug')->ignore($this->category())],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('catalog.slug_format'),
            'parent_id.exists' => __('catalog.categories.parent_missing'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['parent_id' => __('catalog.categories.parent')];
    }

    /**
     * The Category being edited; none while creating one.
     */
    public function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }

    /**
     * @return array{name: string, slug: string|null, parent_id: int|null, description: string|null}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'slug' => $this->filled('slug') ? $this->string('slug')->toString() : null,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
        ];
    }
}
