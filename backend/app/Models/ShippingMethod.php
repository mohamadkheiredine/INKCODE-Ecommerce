<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A way of delivering within a Shipping zone (Standard, Express) at a flat price.
 * Only active methods are offered when quoting.
 *
 * @property int $id
 * @property int $shipping_zone_id
 * @property string $name
 * @property string $price
 * @property int $min_delivery_days
 * @property int $max_delivery_days
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShippingZone $zone
 */
#[Fillable(['name', 'price', 'min_delivery_days', 'max_delivery_days', 'is_active'])]
class ShippingMethod extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_delivery_days' => 'integer',
            'max_delivery_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
