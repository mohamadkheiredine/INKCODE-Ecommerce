<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The new lines, addresses, Shipping method and Coupon code (none removes the Coupon) of
 * a Pending or Confirmed Order. The
 * Customer and Fulfilling warehouse stay. The lines replace the Order's lines: one per
 * Variant, each one already on the Order or a Variant of an Active Product.
 */
class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->order()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customersAddress = Rule::exists('customer_addresses', 'id')->where('customer_id', $this->order()->customer_id);

        return [
            'billing_address_id' => ['required', 'integer', $customersAddress],
            'shipping_address_id' => ['required', 'integer', $customersAddress],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            ...StoreOrderRequest::lineRules($this->order()->lines->pluck('variant_id')->all()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_address_id.exists' => __('orders.address_not_customers'),
            'shipping_address_id.exists' => __('orders.address_not_customers'),
            ...StoreOrderRequest::lineMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'billing_address_id' => __('orders.attributes.billing_address_id'),
            'shipping_address_id' => __('orders.attributes.shipping_address_id'),
            'shipping_method_id' => __('orders.attributes.shipping_method_id'),
            'coupon_code' => __('orders.attributes.coupon_code'),
            ...StoreOrderRequest::lineAttributes(),
        ];
    }

    /**
     * @return array{billing_address_id: int, shipping_address_id: int, shipping_method_id: int, coupon_code: string|null, lines: list<array{variant_id: int, quantity: int}>}
     */
    public function values(): array
    {
        return [
            'billing_address_id' => $this->integer('billing_address_id'),
            'shipping_address_id' => $this->integer('shipping_address_id'),
            'shipping_method_id' => $this->integer('shipping_method_id'),
            'coupon_code' => StoreOrderRequest::couponCodeFrom($this),
            'lines' => StoreOrderRequest::linesFrom($this),
        ];
    }

    private function order(): Order
    {
        $order = $this->route('order');
        abort_unless($order instanceof Order, 404);

        return $order;
    }
}
