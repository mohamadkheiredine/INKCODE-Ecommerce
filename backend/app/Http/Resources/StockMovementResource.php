<?php

namespace App\Http\Resources;

use App\Contracts\Auditable;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deltas are signed. `source` names the record that caused the movement (`type` is its
 * kind, such as `receipt`, and `label` how it reads, such as "RCV-2026-000012").
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $source = $this->source;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'variant' => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'options' => $this->variant->options(),
                'product' => $this->variant->product->only('id', 'name'),
            ],
            'warehouse' => $this->warehouse->only('id', 'name', 'code'),
            'on_hand_delta' => $this->on_hand_delta,
            'reserved_delta' => $this->reserved_delta,
            'on_hand_after' => $this->on_hand_after,
            'reserved_after' => $this->reserved_after,
            'reason' => $this->reason,
            'user' => $this->user?->only('id', 'name'),
            'source' => [
                'type' => $this->source_type,
                'id' => $this->source_id,
                'label' => $source instanceof Auditable ? $source->auditLabel() : null,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
