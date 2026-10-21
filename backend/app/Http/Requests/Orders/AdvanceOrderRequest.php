<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The status to move an Order forward to: Preparing or Ready for shipping. Whether that is
 * the Order's next step is the Order flow's decision (409 `invalid_transition`).
 */
class AdvanceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('transition', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in([OrderStatus::Preparing->value, OrderStatus::ReadyForShipping->value])]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => __('orders.attributes.status')];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->string('status')->toString());
    }
}
