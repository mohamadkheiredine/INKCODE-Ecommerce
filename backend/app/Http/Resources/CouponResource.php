<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Amounts are decimal strings in the Store currency, dates are `Y-m-d`. `state` is
 * `scheduled`, `active` or `expired` today; `times_used` counts the uses not released.
 * One Coupon also has its eligible `products` and `categories`.
 *
 * @mixin Coupon
 */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => $this->value,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'state' => $this->state(),
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'minimum_order_amount' => $this->minimum_order_amount,
            'times_used' => $this->whenHas('times_used', fn (): int => (int) $this->getAttribute('times_used')),
            'products_count' => $this->whenCounted('products'),
            'categories_count' => $this->whenCounted('categories'),
            'products' => $this->whenLoaded('products', fn (): array => $this->products
                ->map(fn (Product $product): array => $product->only('id', 'name'))
                ->values()
                ->all()),
            'categories' => $this->whenLoaded('categories', fn (): array => $this->categories
                ->map(fn (Category $category): array => $category->only('id', 'name'))
                ->values()
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function state(): string
    {
        $today = Carbon::today();

        return match (true) {
            $this->starts_on->greaterThan($today) => 'scheduled',
            $this->ends_on !== null && $this->ends_on->lessThan($today) => 'expired',
            default => 'active',
        };
    }
}
