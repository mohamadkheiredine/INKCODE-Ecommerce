<?php

namespace App\Http\Resources;

use App\Models\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every setting, for the Admin's Settings screen. Field names match the update request.
 *
 * @mixin StoreSettings
 */
class StoreSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->currency_symbol,
            'vat_rate' => $this->vat_rate,
            'deduction_stage' => $this->deduction_stage,
            'return_window_days' => $this->return_window_days,
            'low_stock_threshold' => $this->low_stock_threshold,
            'default_locale' => $this->default_locale,
            'default_theme' => $this->default_theme,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
