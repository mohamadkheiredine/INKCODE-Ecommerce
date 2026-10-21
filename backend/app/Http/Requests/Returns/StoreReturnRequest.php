<?php

namespace App\Http\Requests\Returns;

use App\Enums\Permission;
use App\Enums\ReturnReason;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Return request: the reason, an optional note, and how many units of which of the
 * Order's lines come back (each line once). Whether the Order is Delivered, inside the
 * Return window and has those units left to return is the Return action's decision.
 */
class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::ReturnsRequest->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('order');

        return [
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.StoreOrderRequest::MAX_LINES],
            'lines.*' => ['array:line_id,quantity'],
            'lines.*.line_id' => ['required', 'integer', 'distinct', Rule::exists('order_lines', 'id')->where('order_id', $order instanceof Order ? $order->id : 0)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.StoreOrderRequest::MAX_QUANTITY],
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
            'reason' => __('returns.attributes.reason'),
            'note' => __('returns.attributes.note'),
            'lines' => __('returns.attributes.lines'),
            'lines.*.line_id' => __('returns.attributes.line_id'),
            'lines.*.quantity' => __('returns.attributes.quantity'),
        ];
    }

    /**
     * @return array{reason: ReturnReason, note: string|null, lines: list<array{line_id: int, quantity: int}>}
     */
    public function returnRequest(): array
    {
        /** @var array<int, array{line_id: int|string, quantity: int|string}> $lines */
        $lines = $this->validated('lines');

        return [
            'reason' => ReturnReason::from($this->string('reason')->toString()),
            'note' => $this->filled('note') ? $this->string('note')->trim()->toString() : null,
            'lines' => array_map(fn (array $line): array => ['line_id' => (int) $line['line_id'], 'quantity' => (int) $line['quantity']], array_values($lines)),
        ];
    }
}
