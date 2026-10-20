<?php

namespace App\Http\Resources;

use App\Models\Receipt;
use App\Models\ReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are decimal strings. The list has `units` and `total_cost`; one Receipt has its
 * `lines`.
 *
 * @mixin Receipt
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => $this->warehouse->only('id', 'name', 'code')),
            'supplier_reference' => $this->supplier_reference,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only('id', 'name')),
            'units' => $this->whenHas('units', fn (): int => (int) $this->getAttribute('units')),
            'total_cost' => $this->whenHas('total_cost', fn (): string => (string) ($this->getAttribute('total_cost') ?? '0.00')),
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines
                ->map(fn (ReceiptLine $line): array => [
                    'id' => $line->id,
                    'variant' => [
                        'id' => $line->variant->id,
                        'sku' => $line->variant->sku,
                        'options' => $line->variant->options(),
                        'product' => $line->variant->product->only('id', 'name'),
                    ],
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'line_cost' => bcmul((string) $line->quantity, $line->unit_cost, 2),
                ])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
