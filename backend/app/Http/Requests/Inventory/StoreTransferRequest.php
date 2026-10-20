<?php

namespace App\Http\Requests\Inventory;

use App\Models\Transfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Draft Transfer: two different active Warehouses, optional notes, and one line per
 * Variant (each Variant once) with a quantity. Available is checked when it is sent.
 */
class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transfer::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeWarehouse = Rule::exists('warehouses', 'id')->where('is_active', true);

        return [
            'source_warehouse_id' => ['required', 'integer', $activeWarehouse],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id', $activeWarehouse],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.StoreReceiptRequest::MAX_LINES],
            'lines.*' => ['array:variant_id,quantity'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', 'exists:variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.StoreReceiptRequest::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_warehouse_id.exists' => __('inventory.receipts.warehouse_unavailable'),
            'destination_warehouse_id.exists' => __('inventory.receipts.warehouse_unavailable'),
            'destination_warehouse_id.different' => __('inventory.transfers.same_warehouse'),
            'lines.*.variant_id.distinct' => __('inventory.receipts.variant_repeated'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source_warehouse_id' => __('inventory.attributes.source_warehouse_id'),
            'destination_warehouse_id' => __('inventory.attributes.destination_warehouse_id'),
            'lines' => __('inventory.attributes.lines'),
            'lines.*.variant_id' => __('inventory.attributes.variant_id'),
            'lines.*.quantity' => __('inventory.attributes.quantity'),
        ];
    }

    /**
     * @return array{source_warehouse_id: int, destination_warehouse_id: int, notes: string|null, lines: list<array{variant_id: int, quantity: int}>}
     */
    public function values(): array
    {
        /** @var array<int, array{variant_id: int|string, quantity: int|string}> $lines */
        $lines = $this->validated('lines');

        return [
            'source_warehouse_id' => $this->integer('source_warehouse_id'),
            'destination_warehouse_id' => $this->integer('destination_warehouse_id'),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'lines' => array_map(fn (array $line): array => [
                'variant_id' => (int) $line['variant_id'],
                'quantity' => (int) $line['quantity'],
            ], array_values($lines)),
        ];
    }
}
