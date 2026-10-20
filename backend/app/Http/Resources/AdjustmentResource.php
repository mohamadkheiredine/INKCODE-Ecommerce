<?php

namespace App\Http\Resources;

use App\Models\Adjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `quantity_delta` is signed: negative removed units, positive added them.
 *
 * @mixin Adjustment
 */
class AdjustmentResource extends JsonResource
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
            'variant' => $this->whenLoaded('variant', fn (): array => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'product' => $this->variant->product->only('id', 'name'),
            ]),
            'quantity_delta' => $this->quantity_delta,
            'reason' => $this->reason->value,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only('id', 'name')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
