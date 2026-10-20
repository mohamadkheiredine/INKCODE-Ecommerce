<?php

namespace App\Http\Resources;

use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are decimal strings ("19.90"); `low_stock_threshold` null follows the store
 * default.
 *
 * @mixin Variant
 */
class VariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $this->price,
            'average_cost' => $this->average_cost,
            'low_stock_threshold' => $this->low_stock_threshold,
            'options' => $this->whenLoaded('attributeValues', fn (): array => $this->options()),
            'value_ids' => $this->whenLoaded('attributeValues', fn (): array => $this->attributeValues->modelKeys()),
        ];
    }
}
