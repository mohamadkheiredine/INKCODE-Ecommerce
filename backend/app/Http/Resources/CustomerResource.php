<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Customer with their Lifetime value, last Counted order date and Segment. The list
 * carries Tags; the profile also carries the addresses.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'lifetime_value' => $this->whenHas('lifetime_value'),
            'last_order_at' => $this->whenHas('last_order_at', fn (): ?string => $this->last_order_at?->toIso8601String()),
            'segment' => $this->whenHas('segment', fn (): ?string => $this->segment?->value),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
