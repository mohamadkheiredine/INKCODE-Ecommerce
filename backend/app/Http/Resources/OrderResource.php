<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderNote;
use App\Models\OrderStatusChange;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\ShipmentStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are decimal strings in the Order's own currency. The list has `units`; one Order
 * has its `lines`, `status_history`, `shipments`, `fulfillment` (Shipment progress, separate
 * from the Order status) and `timeline` (status changes, Shipments and notes, oldest
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
            'coupon' => $this->coupon_code === null ? null : [
                'id' => $this->coupon_id,
                'code' => $this->coupon_code,
                'type' => $this->coupon_type?->value,
                'value' => $this->coupon_value,
            ],
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
                    'shipped_quantity' => $line->shipped_quantity,
                    'unit_price' => $line->unit_price,
                    'discount_amount' => $line->discount_amount,
                    'coupon_eligible' => $line->coupon_eligible,
                    'taxable' => $line->taxable,
                    'vat_rate' => $line->vat_rate,
                    'vat_amount' => $line->vat_amount,
                    'line_total' => $line->line_total,
                ])
                ->all()),
            'status_history' => $this->whenLoaded('statusChanges', fn (): array => $this->statusChanges
                ->map(fn (OrderStatusChange $change): array => $this->statusChange($change))
                ->all()),
            'fulfillment' => $this->when($this->relationLoaded('lines') && $this->relationLoaded('shipments'), fn (): array => $this->fulfillment()),
            'shipments' => $this->whenLoaded('shipments', fn (): array => $this->shipments
                ->map(fn (Shipment $shipment): array => [
                    'id' => $shipment->id,
                    'number' => $shipment->number,
                    'status' => $shipment->status->value,
                    'carrier' => $shipment->carrier->only('id', 'name'),
                    'tracking_number' => $shipment->tracking_number,
                    'tracking_url' => $shipment->trackingUrl(),
                    'shipped_at' => $shipment->shipped_at->toIso8601String(),
                    'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                    'lines' => $shipment->lines->map(fn (ShipmentLine $line): array => $line->only('order_line_id', 'quantity'))->all(),
                    'status_history' => $shipment->statusChanges
                        ->map(fn (ShipmentStatusChange $change): array => $this->shipmentStatusChange($change))
                        ->all(),
                ])
                ->all()),
            'timeline' => $this->when(
                $this->relationLoaded('statusChanges') && $this->relationLoaded('orderNotes'),
                fn (): array => $this->timeline(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Status changes, Shipment status changes and notes merged, oldest first (in the same
     * second: status change, then Shipment, then note). Later tickets add payments and Returns.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(): array
    {
        $events = [
            ...$this->statusChanges->map(fn (OrderStatusChange $change): array => ['type' => 'status', ...$this->statusChange($change)]),
            ...($this->relationLoaded('shipments') ? $this->shipments->flatMap(fn (Shipment $shipment) => $shipment->statusChanges->map(fn (ShipmentStatusChange $change): array => [
                'type' => 'shipment',
                ...$this->shipmentStatusChange($change),
                'shipment' => $shipment->only('id', 'number'),
            ]))->all() : []),
            ...$this->orderNotes->map(fn (OrderNote $note): array => [
                'type' => 'note',
                'id' => $note->id,
                'body' => $note->body,
                'user' => $note->user?->only('id', 'name'),
                'created_at' => $note->created_at->toIso8601String(),
            ]),
        ];
        $rank = ['status' => 0, 'shipment' => 1, 'note' => 2];
        usort($events, fn (array $a, array $b): int => [$a['created_at'], $rank[$a['type']], $a['id']] <=> [$b['created_at'], $rank[$b['type']], $b['id']]);

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

    /**
     * Shipment progress, separate from the Order status: `unfulfilled` (nothing shipped),
     * `partially_shipped`, `shipped` (every unit still ordered is in a Shipment) or
     * `delivered` (and every Shipment is delivered), with the units shipped of those ordered.
     *
     * @return array{status: string, shipped_units: int, units: int}
     */
    private function fulfillment(): array
    {
        $units = (int) $this->lines->sum('quantity');
        $shipped = (int) $this->lines->sum('shipped_quantity');

        $status = match (true) {
            $shipped === 0 => 'unfulfilled',
            $shipped < $units => 'partially_shipped',
            $this->shipments->every(fn (Shipment $shipment): bool => $shipment->status === ShipmentStatus::Delivered) => 'delivered',
            default => 'shipped',
        };

        return ['status' => $status, 'shipped_units' => $shipped, 'units' => $units];
    }

    /**
     * @return array{id: int, from_status: string|null, to_status: string, user: array<string, mixed>|null, created_at: string}
     */
    private function shipmentStatusChange(ShipmentStatusChange $change): array
    {
        return [
            'id' => $change->id,
            'from_status' => $change->from_status?->value,
            'to_status' => $change->to_status->value,
            'user' => $change->user?->only('id', 'name'),
            'created_at' => $change->created_at->toIso8601String(),
        ];
    }
}
