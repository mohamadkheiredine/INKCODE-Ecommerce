<?php

namespace App\Http\Resources;

use App\Models\Transfer;
use App\Models\TransferLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The list has `units`; one Transfer has its `lines`.
 *
 * @mixin Transfer
 */
class TransferResource extends JsonResource
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
            'source_warehouse' => $this->whenLoaded('sourceWarehouse', fn (): array => $this->sourceWarehouse->only('id', 'name', 'code')),
            'destination_warehouse' => $this->whenLoaded('destinationWarehouse', fn (): array => $this->destinationWarehouse->only('id', 'name', 'code')),
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only('id', 'name')),
            'units' => $this->whenHas('units', fn (): int => (int) $this->getAttribute('units')),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines
                ->map(fn (TransferLine $line): array => [
                    'id' => $line->id,
                    'variant' => [
                        'id' => $line->variant->id,
                        'sku' => $line->variant->sku,
                        'product' => $line->variant->product->only('id', 'name'),
                    ],
                    'quantity' => $line->quantity,
                ])
                ->all()),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
