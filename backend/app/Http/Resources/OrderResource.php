<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderNote;
use App\Models\OrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are decimal strings in the Order's own currency. The list has `units`; one Order
 * has its `lines`, `status_history` and `timeline` (status changes and notes, oldest
 * first). `allowed_moves` are the statuses the Order flow lets it move to next, and
 * `is_locked` is true from Preparing onward (lines, quantities and addresses are fixed).
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
            'is_locked' => ! $this->status->isEditable(),
            'allowed_moves' => array_map(fn (OrderStatus $status): string => $status->value, $this->status->allowedMoves()),
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
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee?->only('id', 'name')),
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
                    'cancelled_quantity' => $line->cancelled_quantity,
                    'unit_price' => $line->unit_price,
                    'discount_amount' => $line->discount_amount,
                    'taxable' => $line->taxable,
                    'vat_rate' => $line->vat_rate,
                    'vat_amount' => $line->vat_amount,
                    'line_total' => $line->line_total,
                ])
                ->all()),
            'status_history' => $this->whenLoaded('statusChanges', fn (): array => $this->statusChanges
                ->map(fn (OrderStatusChange $change): array => $this->statusChange($change))
                ->all()),
            'timeline' => $this->when(
                $this->relationLoaded('statusChanges') && $this->relationLoaded('orderNotes'),
                fn (): array => $this->timeline(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Status changes and notes merged, oldest first (a status change before a note made in
     * the same second). Later tickets add payments, Shipments and Returns.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(): array
    {
        $events = [
            ...$this->statusChanges->map(fn (OrderStatusChange $change): array => ['type' => 'status', ...$this->statusChange($change)]),
            ...$this->orderNotes->map(fn (OrderNote $note): array => [
                'type' => 'note',
                'id' => $note->id,
                'body' => $note->body,
                'user' => $note->user?->only('id', 'name'),
                'created_at' => $note->created_at->toIso8601String(),
            ]),
        ];
        usort($events, fn (array $a, array $b): int => [$a['created_at'], $a['type'] === 'note', $a['id']] <=> [$b['created_at'], $b['type'] === 'note', $b['id']]);

        return $events;
    }

    /**
     * @return array{id: int, from_status: string|null, to_status: string, note: string|null, user: array<string, mixed>|null, created_at: string}
     */
    private function statusChange(OrderStatusChange $change): array
    {
        return [
            'id' => $change->id,
            'from_status' => $change->from_status?->value,
            'to_status' => $change->to_status->value,
            'note' => $change->note,
            'user' => $change->user?->only('id', 'name'),
            'created_at' => $change->created_at->toIso8601String(),
        ];
    }
}
