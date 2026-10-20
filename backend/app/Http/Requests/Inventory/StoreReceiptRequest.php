<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Catalog\StoreProductRequest;
use App\Models\Receipt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Receipt: an active Warehouse, the supplier's reference, optional notes, and one line
 * per Variant (each Variant once) with the quantity and unit cost (up to four decimals).
 * It is safe to retry with an `Idempotency-Key`.
 */
class StoreReceiptRequest extends FormRequest
{
    public const int MAX_LINES = 100;

    public const int MAX_QUANTITY = 1000000;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Receipt::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'supplier_reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*' => ['array:variant_id,quantity,unit_cost'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', 'exists:variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'lines.*.unit_cost' => ['required', 'decimal:0,4', 'min:0', 'max:'.StoreProductRequest::MAX_PRICE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.exists' => __('inventory.receipts.warehouse_unavailable'),
            'lines.*.variant_id.distinct' => __('inventory.receipts.variant_repeated'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('inventory.attributes.warehouse_id'),
            'supplier_reference' => __('inventory.attributes.supplier_reference'),
            'lines' => __('inventory.attributes.lines'),
            'lines.*.variant_id' => __('inventory.attributes.variant_id'),
            'lines.*.quantity' => __('inventory.attributes.quantity'),
            'lines.*.unit_cost' => __('inventory.attributes.unit_cost'),
        ];
    }

    /**
     * @return array{warehouse_id: int, supplier_reference: string, notes: string|null, lines: list<array{variant_id: int, quantity: int, unit_cost: string}>}
     */
    public function values(): array
    {
        /** @var array<int, array{variant_id: int|string, quantity: int|string, unit_cost: int|float|string}> $lines */
        $lines = $this->validated('lines');

        return [
            'warehouse_id' => $this->integer('warehouse_id'),
            'supplier_reference' => $this->string('supplier_reference')->toString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'lines' => array_map(fn (array $line): array => [
                'variant_id' => (int) $line['variant_id'],
                'quantity' => (int) $line['quantity'],
                'unit_cost' => (string) $line['unit_cost'],
            ], array_values($lines)),
        ];
    }
}
