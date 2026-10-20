<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A validated list request (built by App\Http\Requests\ListRequest): the search term,
 * the filters that were given, the sort and the page size. Each list's query class
 * (an action such as ListCategories) applies its own search and filters, then hands
 * the query to {@see paginate()}.
 */
final readonly class ListParameters
{
    /**
     * @param  array<string, mixed>  $filters  only the filters that were given
     */
    public function __construct(
        public ?string $search,
        public array $filters,
        public string $sortColumn,
        public bool $sortDescending,
        public int $perPage,
    ) {}

    public function hasFilter(string $name): bool
    {
        return array_key_exists($name, $this->filters);
    }

    public function filter(string $name): mixed
    {
        return $this->filters[$name] ?? null;
    }

    /**
     * Keeps the rows where any of the columns contains the search term, typed as is
     * (`%` and `_` match themselves, not any characters).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    public function search(Builder $query, array $columns): Builder
    {
        if ($this->search === null) {
            return $query;
        }

        $pattern = '%'.addcslashes($this->search, '\\%_').'%';

        return $query->where(function (Builder $query) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $pattern);
            }
        });
    }

    /**
     * Sorts (the key, then any extra tie-breaker columns for a list that joins rows,
     * break ties, so rows never jump between pages) and returns the requested page. Every
     * page link keeps the search, filters and sort.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $tieBreakers
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, array $tieBreakers = []): LengthAwarePaginator
    {
        $direction = $this->sortDescending ? 'desc' : 'asc';

        $query
            ->orderBy($this->sortColumn, $direction)
            ->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        foreach ($tieBreakers as $column) {
            $query->orderBy($column, $direction);
        }

        return $query->paginate($this->perPage)->withQueryString();
    }
}
