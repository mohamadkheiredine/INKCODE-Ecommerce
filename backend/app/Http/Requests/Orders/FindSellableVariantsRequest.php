<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A search (SKU or Product name) for the Order form's Variant picker. Open to Roles that
 * create Orders.
 */
class FindSellableVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['search' => ['required', 'string', 'max:100']];
    }
}
