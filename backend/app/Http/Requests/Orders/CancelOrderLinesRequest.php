<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial cancellation: how many units of which of the Order's lines (each line once),
 * with an optional reason added to the timeline.
 */
class CancelOrderLinesRequest extends FormRequest
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
        $order = $this->route('order');

        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.StoreOrderRequest::MAX_LINES],
            'lines.*' => ['array:line_id,quantity'],
            'lines.*.line_id' => ['required', 'integer', 'distinct', Rule::exists('order_lines', 'id')->where('order_id', $order instanceof Order ? $order->id : 0)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.StoreOrderRequest::MAX_QUANTITY],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lines.*.line_id.exists' => __('orders.line_not_on_order')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lines' => __('orders.attributes.lines'),
            'lines.*.line_id' => __('orders.attributes.line_id'),
            'lines.*.quantity' => __('orders.attributes.quantity'),
            'note' => __('orders.attributes.note'),
        ];
    }

    /**
     * @return list<array{line_id: int, quantity: int}>
     */
    public function cancellations(): array
    {
        /** @var array<int, array{line_id: int|string, quantity: int|string}> $lines */
        $lines = $this->validated('lines');

        return array_map(fn (array $line): array => ['line_id' => (int) $line['line_id'], 'quantity' => (int) $line['quantity']], array_values($lines));
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }
}
