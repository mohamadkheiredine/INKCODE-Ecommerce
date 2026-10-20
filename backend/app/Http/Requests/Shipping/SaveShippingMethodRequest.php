<?php

namespace App\Http\Requests\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new or edited Shipping method in a zone: a name unique within the zone, a flat price, the
 * estimated delivery days (from–to) and whether it is offered. Managing a zone's
 * methods is part of managing the zone, so the zone's `update` ability decides.
 */
class SaveShippingMethodRequest extends FormRequest
{
    public const string MAX_PRICE = '99999999.99';

    public const int MAX_DELIVERY_DAYS = 365;

    public function authorize(): bool
    {
        $method = $this->shippingMethod();

        return $this->user()?->can('update', $method ?? $this->zone()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('shipping_methods', 'name')
                ->where('shipping_zone_id', $this->zone()->id)
                ->ignore($this->shippingMethod())],
            'price' => ['required', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE],
            'min_delivery_days' => ['required', 'integer', 'min:0', 'max:'.self::MAX_DELIVERY_DAYS],
            'max_delivery_days' => ['required', 'integer', 'gte:min_delivery_days', 'max:'.self::MAX_DELIVERY_DAYS],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'price' => __('shipping.attributes.price'),
            'min_delivery_days' => __('shipping.attributes.min_delivery_days'),
            'max_delivery_days' => __('shipping.attributes.max_delivery_days'),
        ];
    }

    /**
     * The zone the method belongs to (from the URL when creating, the method's own when editing).
     */
    public function zone(): ShippingZone
    {
        $zone = $this->route('shipping_zone');

        if ($zone instanceof ShippingZone) {
            return $zone;
        }

        return $this->shippingMethod()->zone ?? abort(404);
    }

    /**
     * The Shipping method being edited; none while creating one.
     */
    public function shippingMethod(): ?ShippingMethod
    {
        $method = $this->route('shipping_method');

        return $method instanceof ShippingMethod ? $method : null;
    }

    /**
     * The price keeps the two decimals it was typed with ("7.5" becomes "7.50"); it is
     * never turned into a float.
     *
     * @return array{name: string, price: string, min_delivery_days: int, max_delivery_days: int, is_active: bool}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'price' => bcadd($this->string('price')->trim()->toString(), '0', 2),
            'min_delivery_days' => $this->integer('min_delivery_days'),
            'max_delivery_days' => $this->integer('max_delivery_days'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
