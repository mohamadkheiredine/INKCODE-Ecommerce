<?php

namespace App\Actions\Search;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Builder;

/**
 * The global search: the top matches among Orders (number, Customer), Products (name, SKU)
 * and Customers (name, email, phone). A group appears only when the User's Role may list
 * that module, so the results never reveal records the User could not open.
 */
final class SearchEverything
{
    public const int LIMIT = 5;

    /**
     * @return array{orders?: list<array<string, mixed>>, products?: list<array<string, mixed>>, customers?: list<array<string, mixed>>}
     */
    public function handle(User $user, string $search): array
    {
        $pattern = '%'.addcslashes($search, '\%_').'%';
        $groups = [];

        if ($user->can('viewAny', Order::class)) {
            $groups['orders'] = $this->orders($pattern);
        }

        if ($user->can('viewAny', Product::class)) {
            $groups['products'] = $this->products($search, $pattern);
        }

        if ($user->can('viewAny', Customer::class)) {
            $groups['customers'] = $this->customers($pattern);
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orders(string $pattern): array
    {
        return Order::query()
            ->with('customer:id,name')
            ->where(fn (Builder $query) => $query
                ->where('number', 'like', $pattern)
                ->orWhereIn('customer_id', Customer::query()->select('id')->where(fn (Builder $customers) => $customers
                    ->where('name', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern))))
            ->latest('placed_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'total' => $order->total,
                'customer_name' => $order->customer->name,
                'placed_at' => $order->placed_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(string $search, string $pattern): array
    {
        return Product::query()
            ->with(['mainImage', 'variants' => fn ($variants) => $variants->select('id', 'product_id', 'sku')->orderBy('id')])
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhereRelation('variants', 'sku', 'like', $pattern))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $this->matchingSku($product, $search),
                'status' => $product->status->value,
                'main_image_url' => $product->mainImage?->url(),
            ])
            ->all();
    }

    /**
     * The SKU worth showing: the Variant the search matched, else the first Variant's.
     */
    private function matchingSku(Product $product, string $search): ?string
    {
        $matched = $product->variants->first(fn (Variant $variant): bool => str_contains(mb_strtolower($variant->sku), mb_strtolower($search)));

        return ($matched ?? $product->variants->first())?->sku;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customers(string $pattern): array
    {
        return Customer::query()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhere('phone', 'like', $pattern))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (Customer $customer): array => $customer->only('id', 'name', 'email', 'phone'))
            ->all();
    }
}
