<?php

namespace App\Http\Requests\Catalog;

/**
 * A Product's new details. An empty slug is generated again from the name.
 */
class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        $product = $this->product();

        return $product !== null && ($this->user()?->can('update', $product) ?? false);
    }
}
