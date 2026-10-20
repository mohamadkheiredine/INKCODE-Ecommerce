<?php

namespace App\Http\Requests\Shipping;

use App\Enums\Permission;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address to quote shipping for: a country code and a city. Open to Roles that
 * create Orders (they choose a method for each one) and to those managing shipping.
 */
class QuoteShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission([Permission::OrdersCreate->value, Permission::ShippingManage->value]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country_code' => Countries::normalize($this->input('country_code'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', Countries::rule()],
            'city' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['country_code' => __('shipping.attributes.country'), 'city' => __('shipping.attributes.city')];
    }
}
