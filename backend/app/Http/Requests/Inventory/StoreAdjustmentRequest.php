<?php

namespace App\Http\Requests\Inventory;

use App\Enums\AdjustmentReason;
use App\Models\Adjustment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An Adjustment: an active Warehouse, a Variant, a non-zero quantity (negative removes
 * units) and a reason. It is safe to retry with an `Idempotency-Key`.
 */
class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Adjustment::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'variant_id' => ['required', 'integer', 'exists:variants,id'],
            'quantity_delta' => ['required', 'integer', 'not_in:0', 'between:-'.StoreReceiptRequest::MAX_QUANTITY.','.StoreReceiptRequest::MAX_QUANTITY],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.exists' => __('inventory.receipts.warehouse_unavailable'),
            'quantity_delta.not_in' => __('inventory.adjustments.not_zero'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('inventory.attributes.warehouse_id'),
            'variant_id' => __('inventory.attributes.variant_id'),
            'quantity_delta' => __('inventory.attributes.quantity'),
            'reason' => __('inventory.attributes.reason'),
        ];
    }

    /**
     * @return array{warehouse_id: int, variant_id: int, quantity_delta: int, reason: AdjustmentReason, notes: string|null}
     */
    public function values(): array
    {
        return [
            'warehouse_id' => $this->integer('warehouse_id'),
            'variant_id' => $this->integer('variant_id'),
            'quantity_delta' => $this->integer('quantity_delta'),
            'reason' => $this->enum('reason', AdjustmentReason::class) ?? AdjustmentReason::CountCorrection,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
