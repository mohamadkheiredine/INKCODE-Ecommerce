<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\Variant;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of Products, each with its Category, Brand, main image, Variant count and
 * price range. A Product's price is its lowest Variant price, or its base price while
 * it has no Variants; the price filter and sort use it.
 */
final class ListProducts
{
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = Product::query()
            ->select('products.*')
            ->selectSub(
                Variant::query()->selectRaw('coalesce(min(price), products.base_price)')->whereColumn('product_id', 'products.id'),
                'price',
            )
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'mainImage'])
            ->withCount('variants')
            ->withMin('variants', 'price')
            ->withMax('variants', 'price');

        if ($parameters->search !== null) {
            $query->where(function (Builder $query) use ($parameters): void {
                $parameters->search($query, ['name', 'slug']);
                $query->orWhereHas('variants', fn (Builder $variants) => $parameters->search($variants, ['sku']));
            });
        }

        if ($parameters->hasFilter('category_id')) {
            $categoryId = (int) $parameters->filter('category_id');
            $query->whereIn('category_id', fn ($categories) => $categories->select('id')->from('categories')
                ->where('id', $categoryId)->orWhere('parent_id', $categoryId));
        }

        if ($parameters->hasFilter('brand_id')) {
            $query->where('brand_id', (int) $parameters->filter('brand_id'));
        }

        if ($parameters->hasFilter('status')) {
            $query->where('status', $parameters->filter('status'));
        }

        $this->filterByPrice($query, $parameters->filter('price_min'), $parameters->filter('price_max'));

        return $parameters->paginate($query);
    }

    /**
     * Keeps Products with a Variant priced in the range, or without Variants and a base
     * price in the range. Prices compare as decimals in SQL, never as floats.
     *
     * @param  Builder<Product>  $query
     */
    private function filterByPrice(Builder $query, mixed $min, mixed $max): void
    {
        if ($min === null && $max === null) {
            return;
        }

        $inRange = function (Builder $query, string $column) use ($min, $max): void {
            if ($min !== null) {
                $query->whereRaw("$column >= cast(? as decimal(12, 2))", [(string) $min]);
            }
            if ($max !== null) {
                $query->whereRaw("$column <= cast(? as decimal(12, 2))", [(string) $max]);
            }
        };

        $query->where(fn (Builder $query) => $query
            ->whereHas('variants', fn (Builder $variants) => $inRange($variants, 'price'))
            ->orWhere(fn (Builder $query) => $inRange($query->whereDoesntHave('variants'), 'base_price')));
    }
}
