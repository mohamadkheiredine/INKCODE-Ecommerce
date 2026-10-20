<?php

namespace App\Actions\Shipping;

use App\Models\ShippingZone;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Shipping zone, or renames one and replaces the places it covers. Validation
 * already refused places another zone covers; a simultaneous save of the same place is
 * caught by the unique index and answered the same way.
 */
final class SaveShippingZone
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, locations: list<array{country_code: string, city: string|null}>}  $values
     */
    public function handle(?ShippingZone $zone, array $values): ShippingZone
    {
        return DatabaseErrors::refuseDuplicateOnIndexes(fn (): ShippingZone => DB::transaction(function () use ($zone, $values): ShippingZone {
            $before = $zone?->auditValues();
            $zone ??= new ShippingZone;

            $zone->fill(['name' => $values['name']])->save();
            $zone->locations()->delete();
            $zone->locations()->createMany($values['locations']);

            $before === null ? $this->auditTrail->created($zone) : $this->auditTrail->updated($zone, $before);

            return $zone->load('locations')->loadCount('methods');
        }), [
            'shipping_zones_name_unique' => ['name', __('validation.unique', ['attribute' => 'name'])],
            'shipping_zone_locations_country_code_city_key_unique' => ['locations', __('shipping.zones.location_taken_meanwhile')],
        ]);
    }
}
