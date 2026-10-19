<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A place a Shipping zone covers: a whole country (no city) or one city in a country.
 * Saved and audited with its zone.
 *
 * @property int $id
 * @property int $shipping_zone_id
 * @property string $country_code
 * @property string|null $city
 * @property string $city_key
 */
#[Fillable(['country_code', 'city'])]
class ShippingZoneLocation extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function label(): string
    {
        return $this->city === null ? $this->country_code : "{$this->country_code}/{$this->city}";
    }
}
