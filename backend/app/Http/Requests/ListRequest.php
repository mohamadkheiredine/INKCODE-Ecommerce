<?php

namespace App\Http\Requests;

use App\Support\ListParameters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The one query convention every list endpoint shares:
 * `search`, `filter[name]=value`, `sort=name` or `sort=-name` (descending), `page` and
 * `per_page`. A list declares what it allows; anything else is refused with a 422 that
 * names what is allowed, so a typo never silently returns an unfiltered list.
 */
abstract class ListRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    /**
     * The fields the list can be sorted by. Each one is a column or a selected alias.
     *
     * @return list<string>
     */
    abstract protected function sorts(): array;

    /**
     * The sort used when none is asked for, for example `name` or `-created_at`.
     */
    abstract protected function defaultSort(): string;

    /**
     * The filters the list accepts, each with its validation rules.
     *
     * @return array<string, array<mixed>>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sorts = $this->sorts();
        $filterRules = [];
        foreach ($this->filters() as $name => $rules) {
            $filterRules["filter.$name"] = ['nullable', ...$rules];
        }

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in([...$sorts, ...array_map(fn (string $sort): string => "-$sort", $sorts)])],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'between:1,'.self::MAX_PER_PAGE],
            'filter' => $this->filters() === [] ? ['prohibited'] : ['array:'.implode(',', array_keys($this->filters()))],
            ...$filterRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => __('lists.unknown_sort', ['sorts' => implode(', ', $this->sorts())]),
            'filter.prohibited' => __('lists.no_filters'),
            'filter.array' => __('lists.unknown_filter', ['filters' => implode(', ', array_keys($this->filters()))]),
        ];
    }

    /**
     * What the User asked for, once validated.
     */
    public function listParameters(): ListParameters
    {
        $sort = $this->string('sort')->toString() ?: $this->defaultSort();
        $search = trim($this->string('search')->toString());

        /** @var array<string, mixed> $filters */
        $filters = array_filter(
            (array) $this->validated('filter', []),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return new ListParameters(
            search: $search === '' ? null : $search,
            filters: $filters,
            sortColumn: ltrim($sort, '-'),
            sortDescending: str_starts_with($sort, '-'),
            perPage: $this->integer('per_page', self::DEFAULT_PER_PAGE),
        );
    }
}
