<?php

namespace App\Http\Requests\Catalog;

/**
 * An Attribute's new name and its whole new list of values.
 */
class UpdateAttributeRequest extends StoreAttributeRequest
{
    public function authorize(): bool
    {
        $attribute = $this->attributeBeingEdited();

        return $attribute !== null && ($this->user()?->can('update', $attribute) ?? false);
    }
}
