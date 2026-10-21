<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\CouponType;
use App\Models\Concerns\Audited;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A code that discounts an Order by a percentage or a fixed amount, from `starts_on` to
 * `ends_on` (both days included; no end date means it never expires), within its total
 * and per-Customer usage limits and above its minimum order amount. With eligible
 * Products or Categories it discounts only those lines (a parent Category includes its
 * children); with none, every line. An Order carries at most one Coupon.
 *
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property string $value
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property string $minimum_order_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, CouponRedemption> $redemptions
 */
#[Fillable(['code', 'type', 'value', 'starts_on', 'ends_on', 'usage_limit', 'per_customer_limit', 'minimum_order_amount'])]
class Coupon extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'ends_on' => null,
        'usage_limit' => null,
        'per_customer_limit' => null,
        'minimum_order_amount' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'minimum_order_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function auditLabel(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            ...$this->only('code', 'value', 'usage_limit', 'per_customer_limit', 'minimum_order_amount'),
            'type' => $this->type->value,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'product_ids' => $this->products()->orderBy('products.id')->pluck('products.id')->all(),
            'category_ids' => $this->categories()->orderBy('categories.id')->pluck('categories.id')->all(),
        ];
    }
}
