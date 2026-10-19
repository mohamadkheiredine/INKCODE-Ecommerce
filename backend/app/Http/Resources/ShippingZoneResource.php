<?php

namespace App\Http\Resources;

use App\Models\ShippingZone;
use App\Models\ShippingZoneLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Shipping zone, the places it covers (a null city means the whole country) and, on
 * its own page, its Shipping methods.
 *
 * @mixin ShippingZone
 */
class ShippingZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'locations' => $this->whenLoaded('locations', fn (): array => $this->locations
                ->map(fn (ShippingZoneLocation $location): array => [
                    'country_code' => $location->country_code,
                    'city' => $location->city,
                ])
                ->all()),
            'methods_count' => $this->whenCounted('methods'),
            'active_methods_count' => $this->whenCounted('active_methods'),
            'methods' => ShippingMethodResource::collection($this->whenLoaded('methods')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
