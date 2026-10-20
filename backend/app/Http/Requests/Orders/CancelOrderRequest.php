<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling an Order, with an optional reason kept in its status history.
 */
class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('cancel', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('orders.attributes.note')];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }
}
