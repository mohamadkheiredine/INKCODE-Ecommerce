<?php

namespace App\Http\Resources;

use App\Models\OrderReturn;
use App\Models\ReturnLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Return. Amounts are decimal strings in the Order's currency. The list has `units`;
 * one Return has its `lines` (each with what the Customer paid for its units and its
 * Restock decision) and `refund_suggestion`: the items' amount, the Order's shipping (null
 * once another Return refunded it) and `refundable`, the Order's Captured amount minus
 * earlier Refunds. A Refund may be at most the lower of the suggestion and `refundable`.
 *
 * @mixin OrderReturn
 */
class ReturnResource extends JsonResource
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
            'reason' => $this->reason->value,
            'note' => $this->note,
            'decision_note' => $this->decision_note,
            'refund_shipping' => $this->refund_shipping,
            'refund_amount' => $this->refund_amount,
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'currency_code' => $this->order->currency_code,
                'customer' => $this->order->relationLoaded('customer') ? $this->order->customer->only('id', 'name') : null,
            ]),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only('id', 'name')),
            'refund' => $this->whenLoaded('refund', fn (): ?array => $this->refund === null ? null : (new PaymentResource($this->refund))->resolve($request)),
            'units' => $this->whenHas('units', fn (): int => (int) $this->getAttribute('units')),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines
                ->map(fn (ReturnLine $line): array => [
                    'id' => $line->id,
                    'order_line_id' => $line->order_line_id,
                    'product_name' => $line->orderLine->product_name,
                    'sku' => $line->orderLine->sku,
                    'options' => array_map(fn (array $option): array => ['attribute' => $option['attribute'], 'value' => $option['value']], $line->orderLine->options),
                    'quantity' => $line->quantity,
                    'ordered_quantity' => $line->orderLine->quantity,
                    'paid_amount' => $line->paidAmount(),
                    'restock_decision' => $line->restock_decision?->value,
                    'decided_at' => $line->decided_at?->toIso8601String(),
                ])
                ->all()),
            'refund_suggestion' => $this->when($this->relationLoaded('lines') && $this->relationLoaded('order'), fn (): array => [
                'items' => $this->suggestedRefund(false),
                'shipping' => $this->refund_shipping || ! $this->order->shippingRefunded() ? $this->order->shipping_total : null,
                'refundable' => $this->order->netCaptured(),
            ]),
            'requested_at' => $this->requested_at->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
