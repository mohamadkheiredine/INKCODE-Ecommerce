<?php

namespace App\Http\Requests\Shipping;

/**
 * A Shipping zone's new name and the full list of places it covers (replacing the old one).
 */
class UpdateShippingZoneRequest extends StoreShippingZoneRequest
{
    public function authorize(): bool
    {
        $zone = $this->zone();

        return $zone !== null && ($this->user()?->can('update', $zone) ?? false);
    }
}
