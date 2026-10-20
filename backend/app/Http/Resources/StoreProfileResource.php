<?php

namespace App\Http\Resources;

use App\Models\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public part of the settings: what every signed-in User's screens show.
 *
 * @mixin StoreSettings
 */
class StoreProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'currency' => [
                'code' => $this->currency_code,
                'symbol' => $this->currency_symbol,
            ],
        ];
    }
}
