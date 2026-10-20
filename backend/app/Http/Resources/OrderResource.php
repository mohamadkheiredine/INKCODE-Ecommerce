<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are decimal strings in the Order's own currency. The list has `units`; one Order
 * has its `lines` and `status_history`.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'placed_at' => $this->placed_at->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn (): array => $this->customer->only('id', 'name', 'email', 'phone')),
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => $this->warehouse->only('id', 'name', 'code')),
            'shipping_method' => ['id' => $this->shipping_method_id, 'name' => $this->shipping_method_name],
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'currency_code' => $this->currency_code,
            'vat_rate' => $this->vat_rate,
            'items_total' => $this->items_total,
            'discount_total' => $this->discount_total,
            'vat_total' => $this->vat_total,
            'shipping_total' => $this->shipping_total,
            'total' => $this->total,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only('id', 'name')),
            'units' => $this->whenHas('units', fn (): int => (int) $this->getAttribute('units')),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines
                ->map(fn (OrderLine $line): array => [
                    'id' => $line->id,
                    'variant_id' => $line->variant_id,
                    'product_id' => $line->relationLoaded('variant') ? $line->variant->product_id : null,
                    'product_name' => $line->product_name,
                    'sku' => $line->sku,
                    // MySQL's JSON type reorders keys, so each option is rebuilt in reading order.
                    'options' => array_map(fn (array $option): array => ['attribute' => $option['attribute'], 'value' => $option['value']], $line->options),
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'discount_amount' => $line->discount_amount,
                    'taxable' => $line->taxable,
                    'vat_rate' => $line->vat_rate,
                    'vat_amount' => $line->vat_amount,
                    'line_total' => $line->line_total,
                ])
                ->all()),
            'status_history' => $this->whenLoaded('statusChanges', fn (): array => $this->statusChanges
                ->map(fn (OrderStatusChange $change): array => [
                    'id' => $change->id,
                    'from_status' => $change->from_status?->value,
                    'to_status' => $change->to_status->value,
                    'note' => $change->note,
                    'user' => $change->user?->only('id', 'name'),
                    'created_at' => $change->created_at->toIso8601String(),
                ])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
