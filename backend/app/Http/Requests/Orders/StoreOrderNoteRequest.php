<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use App\Models\OrderNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A note on an Order's timeline, in plain text. Locked orders still take notes.
 */
class StoreOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('update', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:'.OrderNote::MAX_LENGTH]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => __('orders.attributes.body')];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->toString();
    }
}
