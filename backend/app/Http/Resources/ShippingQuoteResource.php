<?php

namespace App\Http\Resources;

use App\Actions\Shipping\ShippingQuote;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Shipping methods offered for an address. When none are, `zone` or `methods` is
 * empty and `message` says why, in the interface language.
 *
 * @property ShippingQuote $resource
 */
class ShippingQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'zone' => $quote->zone === null ? null : ['id' => $quote->zone->id, 'name' => $quote->zone->name],
            'methods' => array_map(fn (ShippingMethod $method): array => [
                'id' => $method->id,
                'name' => $method->name,
                'price' => $method->price,
                'min_delivery_days' => $method->min_delivery_days,
                'max_delivery_days' => $method->max_delivery_days,
            ], $quote->methods),
            'message' => $quote->unavailableReason(),
        ];
    }
}
