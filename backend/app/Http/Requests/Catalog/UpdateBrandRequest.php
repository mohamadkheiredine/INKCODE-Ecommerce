<?php

namespace App\Http\Requests\Catalog;

/**
 * A Brand's new name and slug. An empty slug is generated again from the name.
 */
class UpdateBrandRequest extends StoreBrandRequest
{
    public function authorize(): bool
    {
        $brand = $this->brand();

        return $brand !== null && ($this->user()?->can('update', $brand) ?? false);
    }
}
