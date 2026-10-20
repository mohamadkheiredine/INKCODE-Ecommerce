<?php

namespace App\Http\Resources;

use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Shipping method. The price is a decimal string ("7.50"), never a float.
 *
 * @mixin ShippingMethod
 */
class ShippingMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipping_zone_id' => $this->shipping_zone_id,
            'name' => $this->name,
            'price' => $this->price,
            'min_delivery_days' => $this->min_delivery_days,
            'max_delivery_days' => $this->max_delivery_days,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
