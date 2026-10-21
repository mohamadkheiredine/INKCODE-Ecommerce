<?php

namespace App\Http\Requests\Shipments;

use App\Http\Requests\Orders\StoreOrderRequest;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Shipment: its Carrier, tracking number (unique per Carrier) and how many units of which
 * of the Order's lines it carries (each line once). Whether those units are still
 * unshipped is the Shipment action's decision.
 */
class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('ship', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $order = $this->route('order');

        return [
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')],
            'tracking_number' => [
                'required', 'string', 'max:100',
                Rule::unique('shipments', 'tracking_number')->where('carrier_id', $this->integer('carrier_id')),
            ],
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
        return [
            'lines.*.line_id.exists' => __('orders.line_not_on_order'),
            'tracking_number.unique' => __('shipments.tracking_number_taken'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'carrier_id' => __('shipments.attributes.carrier_id'),
            'tracking_number' => __('shipments.attributes.tracking_number'),
            'lines' => __('shipments.attributes.lines'),
            'lines.*.line_id' => __('shipments.attributes.line_id'),
            'lines.*.quantity' => __('shipments.attributes.quantity'),
        ];
    }

    /**
     * @return array{carrier_id: int, tracking_number: string, lines: list<array{line_id: int, quantity: int}>}
     */
    public function shipment(): array
    {
        /** @var array<int, array{line_id: int|string, quantity: int|string}> $lines */
        $lines = $this->validated('lines');

        return [
            'carrier_id' => $this->integer('carrier_id'),
            'tracking_number' => $this->string('tracking_number')->trim()->toString(),
            'lines' => array_map(fn (array $line): array => ['line_id' => (int) $line['line_id'], 'quantity' => (int) $line['quantity']], array_values($lines)),
        ];
    }
}
