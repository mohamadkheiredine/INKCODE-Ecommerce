<?php

namespace App\Http\Requests\Inventory;

/**
 * A Warehouse's new name, code, address and active state.
 */
class UpdateWarehouseRequest extends StoreWarehouseRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->warehouse();

        return $warehouse !== null && ($this->user()?->can('update', $warehouse) ?? false);
    }
}
