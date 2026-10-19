<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates a slug from a name that no other row of the same table uses yet:
 * "Summer Collection" → "summer-collection", then "summer-collection-2", "-3"… Names
 * with no Latin letters or digits after transliteration fall back to a plain word. The
 * table's unique index stays the final guard against two saves at the same moment.
 */
final class UniqueSlug
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  rows whose slugs are taken (exclude the record being renamed)
     */
    public static function from(string $name, Builder $query, string $fallback, string $column = 'slug'): string
    {
        $base = Str::slug($name) ?: $fallback;

        $taken = (clone $query)
            ->where(fn (Builder $query) => $query->where($column, $base)->orWhere($column, 'like', $base.'-%'))
            ->pluck($column)
            ->flip();

        $slug = $base;
        for ($suffix = 2; $taken->has($slug); $suffix++) {
            $slug = "$base-$suffix";
        }

        return $slug;
    }
}
