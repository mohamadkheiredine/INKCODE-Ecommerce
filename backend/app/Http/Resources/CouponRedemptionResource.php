<?php

namespace App\Http\Resources;

use App\Models\CouponRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One use of a Coupon: the Order and Customer, the discount it gave, when the Order was
 * Confirmed and, if it was fully cancelled since, when the use was released.
 *
 * @mixin CouponRedemption
 */
class CouponRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'status' => $this->order->status->value,
                'total' => $this->order->total,
                'currency_code' => $this->order->currency_code,
            ],
            'customer' => $this->customer->only('id', 'name', 'email'),
            'discount_amount' => $this->discount_amount,
            'redeemed_at' => $this->redeemed_at->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
        ];
    }
}
