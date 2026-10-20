<?php

namespace App\Http\Requests\Shipping;

/**
 * A Carrier's new name and tracking link template.
 */
class UpdateCarrierRequest extends StoreCarrierRequest
{
    public function authorize(): bool
    {
        $carrier = $this->carrier();

        return $carrier !== null && ($this->user()?->can('update', $carrier) ?? false);
    }
}
