<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The lines of an Order being filled in, and optionally its Shipping method, Coupon code
 * and Customer (for the Coupon's per-Customer limit), to quote. Open to Roles that create
 * Orders.
 */
class QuoteOrderRequest extends FormRequest
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
        return [
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            ...StoreOrderRequest::lineRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreOrderRequest::lineMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipping_method_id' => __('orders.attributes.shipping_method_id'),
            'coupon_code' => __('orders.attributes.coupon_code'),
            'customer_id' => __('orders.attributes.customer_id'),
            ...StoreOrderRequest::lineAttributes(),
        ];
    }

    /**
     * @return list<array{variant_id: int, quantity: int}>
     */
    public function lines(): array
    {
        return StoreOrderRequest::linesFrom($this);
    }

    public function shippingMethodId(): ?int
    {
        return $this->filled('shipping_method_id') ? $this->integer('shipping_method_id') : null;
    }

    public function couponCode(): ?string
    {
        return StoreOrderRequest::couponCodeFrom($this);
    }

    public function customerId(): ?int
    {
        return $this->filled('customer_id') ? $this->integer('customer_id') : null;
    }
}
