<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One use of a Coupon: counted when its Order is Confirmed, released (kept, with
 * `released_at`) when the Order is fully cancelled. Usage limits count the redemptions
 * not released.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property int $customer_id
 * @property string $discount_amount
 * @property Carbon $redeemed_at
 * @property Carbon|null $released_at
 * @property-read Coupon $coupon
 * @property-read Order $order
 * @property-read Customer $customer
 */
#[Fillable(['coupon_id', 'order_id', 'customer_id', 'discount_amount', 'redeemed_at', 'released_at'])]
class CouponRedemption extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'redeemed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
