<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;
use App\Support\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A new Category: a name, an optional slug (generated from the name when left out),
 * an optional top-level parent and an optional description.
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
     * Categories go two levels deep at most: the parent must be a top-level Category.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parent = $this->chosenParent($validator);
                if ($parent !== null && ! $parent->isTopLevel()) {
                    $validator->errors()->add('parent_id', __('catalog.categories.parent_is_child', ['name' => $parent->name]));
                }
            },
        ];
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
     * The chosen parent, once its id has passed validation.
     */
    protected function chosenParent(Validator $validator): ?Category
    {
        if (! $this->filled('parent_id') || $validator->errors()->has('parent_id')) {
            return null;
        }

        return Category::query()->find($this->integer('parent_id'));
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
